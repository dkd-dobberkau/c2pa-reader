<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Unit\Service;

use Dkd\C2paReader\Service\CredentialReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Läuft gegen die echten Testbilder in Tests/Fixtures und den echten Verifier.
 */
final class CredentialReaderTest extends UnitTestCase
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/';

    private function file(string $path, string $mimeType, ?int $size = null): FileInterface
    {
        $file = $this->createStub(FileInterface::class);
        $file->method('getMimeType')->willReturn($mimeType);
        $file->method('getSize')->willReturn($size ?? (int)filesize($path));
        $file->method('getForLocalProcessing')->willReturn($path);

        return $file;
    }

    /**
     * @return iterable<string, array{string, string, string, int, string}>
     */
    public static function fixtureProvider(): iterable
    {
        yield 'AI image from ChatGPT' => ['ai/chatgpt-gpt-image.png', 'image/png', CredentialReader::STATUS_VALID, 1, 'OpenAI Media Service API, ChatGPT gpt-image'];
        yield 'created, algorithmicMedia (no trained model)' => ['c2pa-rs/C.jpg', 'image/jpeg', CredentialReader::STATUS_VALID, 0, 'make_test_images 0.33.1, c2pa-rs 0.33.1, Make Test Images 0.33.1'];
        yield 'opened and edited' => ['c2pa-rs/CA.jpg', 'image/jpeg', CredentialReader::STATUS_VALID, 0, 'make_test_images 0.33.1, c2pa-rs 0.33.1'];
        yield 'broken claim signature' => ['c2pa-rs/E-sig-CA.jpg', 'image/jpeg', CredentialReader::STATUS_INVALID, 0, 'make_test_images 0.33.1, c2pa-rs 0.33.1'];
        yield 'expired signing certificate' => ['c2pa-rs/exp-test1.png', 'image/png', CredentialReader::STATUS_INVALID, 0, 'Adobe_Photoshop/23.5.0 (build 20220720.m.1828 0ac45e5; mac)'];
        yield 'JPEG without manifest' => ['c2pa-rs/no_manifest.jpg', 'image/jpeg', CredentialReader::STATUS_NONE, 0, ''];
        yield 'PNG without manifest' => ['c2pa-rs/libpng-test.png', 'image/png', CredentialReader::STATUS_NONE, 0, ''];
    }

    #[Test]
    #[DataProvider('fixtureProvider')]
    public function readReturnsExpectedFieldsForFixture(string $fixture, string $mimeType, string $status, int $ai, string $agents): void
    {
        $before = time();
        $result = (new CredentialReader(new NullLogger()))->read($this->file(self::FIXTURES . $fixture, $mimeType));

        self::assertSame($status, $result['tx_c2pareader_status']);
        self::assertSame($ai, $result['tx_c2pareader_ai']);
        self::assertSame($agents, $result['tx_c2pareader_agents']);
        self::assertGreaterThanOrEqual($before, $result['tx_c2pareader_checked']);
    }

    #[Test]
    public function resultContainsExactlyTheProtectedFields(): void
    {
        $result = (new CredentialReader(new NullLogger()))->read($this->file(self::FIXTURES . 'c2pa-rs/C.jpg', 'image/jpeg'));

        self::assertSame(CredentialReader::FIELDS, array_keys($result));
    }

    #[Test]
    public function unsupportedMimeTypeIsNotSupportedAndUnreadable(): void
    {
        $file = $this->file(self::FIXTURES . 'c2pa-rs/C.jpg', 'image/webp');
        $reader = new CredentialReader(new NullLogger());

        self::assertFalse($reader->supports($file));
        self::assertSame(CredentialReader::STATUS_UNREADABLE, $reader->read($file)['tx_c2pareader_status']);
    }

    #[Test]
    public function fileAboveSizeLimitIsNotRead(): void
    {
        $file = $this->createMock(FileInterface::class);
        $file->method('getMimeType')->willReturn('image/png');
        $file->method('getSize')->willReturn(50 * 1024 * 1024 + 1);
        $file->expects(self::never())->method('getForLocalProcessing');
        $reader = new CredentialReader(new NullLogger());

        self::assertFalse($reader->supports($file));
        self::assertSame(CredentialReader::STATUS_UNREADABLE, $reader->read($file)['tx_c2pareader_status']);
    }

    #[Test]
    public function aiFlagIsNotSetWhenSignatureIsInvalid(): void
    {
        // Das ChatGPT-Bild mit einem veränderten Byte im Bildinhalt: Manifest noch da, Hash passt nicht mehr.
        $bytes = (string)file_get_contents(self::FIXTURES . 'ai/chatgpt-gpt-image.png');
        $offset = strlen($bytes) - 64;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0xFF);
        $tampered = tempnam(sys_get_temp_dir(), 'c2pa-test');
        file_put_contents($tampered, $bytes);

        try {
            $result = (new CredentialReader(new NullLogger()))->read($this->file($tampered, 'image/png'));
        } finally {
            unlink($tampered);
        }

        self::assertSame(CredentialReader::STATUS_INVALID, $result['tx_c2pareader_status']);
        self::assertSame(0, $result['tx_c2pareader_ai']);
    }

    #[Test]
    public function readErrorIsLoggedAndReportedAsUnreadable(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');
        $missing = $this->createStub(FileInterface::class);
        $missing->method('getMimeType')->willReturn('image/jpeg');
        $missing->method('getSize')->willReturn(1);
        $missing->method('hasProperty')->willReturn(true);
        $missing->method('getProperty')->willReturn(42);
        $missing->method('getForLocalProcessing')->willThrowException(new \RuntimeException('storage offline'));

        $result = (new CredentialReader($logger))->read($missing);

        self::assertSame(CredentialReader::STATUS_UNREADABLE, $result['tx_c2pareader_status']);
    }
}
