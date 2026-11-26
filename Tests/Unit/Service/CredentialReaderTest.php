<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Unit\Service;

use Dkd\C2paReader\Configuration\ExtensionSettings;
use Dkd\C2paReader\Service\CredentialReader;
use Dkd\C2paReader\Trust\TrustSettingsProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Runs against the real test files in Tests/Fixtures and the real verifier.
 */
final class CredentialReaderTest extends UnitTestCase
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/';
    private const AGENTS_FORMATS = 'c2pa_reader test fixtures 1, c2pa_reader fixture generator';

    /**
     * A reader without trust lists (an empty trust directory), or with the snapshot in
     * Tests/Fixtures/trust-list.
     */
    private function reader(bool $withTrustLists = false, int $maxFileSizeMb = 50, ?LoggerInterface $logger = null): CredentialReader
    {
        $directory = $withTrustLists ? self::FIXTURES . 'trust-list' : sys_get_temp_dir() . '/c2pa-reader-no-trust-' . getmypid();
        $settings = new ExtensionSettings($directory, $maxFileSizeMb);

        return new CredentialReader($logger ?? new NullLogger(), $settings, new TrustSettingsProvider($settings, new NullLogger()));
    }

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
        $result = $this->reader()->read($this->file(self::FIXTURES . $fixture, $mimeType));

        self::assertSame($status, $result['tx_c2pareader_status']);
        self::assertSame($ai, $result['tx_c2pareader_ai']);
        self::assertSame($agents, $result['tx_c2pareader_agents']);
        self::assertGreaterThanOrEqual($before, $result['tx_c2pareader_checked']);
    }

    /**
     * Signed with the c2pa-rs test certificate (valid until 2030-08-26); see
     * Build/Scripts/make-format-fixtures.sh.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function formatProvider(): iterable
    {
        yield 'WebP' => ['ai.webp', 'image/webp'];
        yield 'GIF' => ['ai.gif', 'image/gif'];
        yield 'AVIF' => ['ai.avif', 'image/avif'];
        yield 'HEIC' => ['ai.heic', 'image/heic'];
        yield 'MP4' => ['ai.mp4', 'video/mp4'];
        yield 'MOV' => ['ai.mov', 'video/quicktime'];
        yield 'WAV' => ['ai.wav', 'audio/x-wav'];
        yield 'MP3' => ['ai.mp3', 'audio/mpeg'];
        yield 'M4A' => ['ai.m4a', 'audio/x-m4a'];
    }

    #[Test]
    #[DataProvider('formatProvider')]
    public function signedAiFileIsValidAndFlaggedInEveryFormat(string $fixture, string $mimeType): void
    {
        $file = $this->file(self::FIXTURES . 'formats/' . $fixture, $mimeType);
        $reader = $this->reader();

        self::assertTrue($reader->supports($file));
        $result = $reader->read($file);
        self::assertSame(CredentialReader::STATUS_VALID, $result['tx_c2pareader_status']);
        self::assertSame(1, $result['tx_c2pareader_ai']);
        self::assertSame(self::AGENTS_FORMATS, $result['tx_c2pareader_agents']);
    }

    #[Test]
    #[DataProvider('formatProvider')]
    public function changedByteMakesTheFileInvalidInEveryFormat(string $fixture, string $mimeType): void
    {
        // At a quarter of the file: in the signed content or the signed manifest for every format here.
        $bytes = (string)file_get_contents(self::FIXTURES . 'formats/' . $fixture);
        $offset = intdiv(strlen($bytes), 4);
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0xFF);
        $tampered = tempnam(sys_get_temp_dir(), 'c2pa-test');
        file_put_contents($tampered, $bytes);

        try {
            $result = $this->reader()->read($this->file($tampered, $mimeType));
        } finally {
            unlink($tampered);
        }

        self::assertSame(CredentialReader::STATUS_INVALID, $result['tx_c2pareader_status']);
        self::assertSame(0, $result['tx_c2pareader_ai']);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function issueProvider(): iterable
    {
        yield 'broken claim signature' => ['c2pa-rs/E-sig-CA.jpg', 'image/jpeg', 'claimSignature.mismatch'];
        yield 'expired signing certificate' => ['c2pa-rs/exp-test1.png', 'image/png', 'signingCredential.expired,signingCredential.invalid'];
        yield 'valid file has none' => ['c2pa-rs/C.jpg', 'image/jpeg', ''];
        yield 'file without manifest has none' => ['c2pa-rs/no_manifest.jpg', 'image/jpeg', ''];
    }

    #[Test]
    #[DataProvider('issueProvider')]
    public function failureCodesExplainAnInvalidStatus(string $fixture, string $mimeType, string $issues): void
    {
        $result = $this->reader()->read($this->file(self::FIXTURES . $fixture, $mimeType));

        self::assertSame($issues, $result['tx_c2pareader_issues']);
    }

    #[Test]
    public function untrustedSignerIsNotAnIssue(): void
    {
        // Without trust lists every signer is untrusted; that alone must not show up as a problem.
        $result = $this->reader()->read($this->file(self::FIXTURES . 'formats/ai.webp', 'image/webp'));

        self::assertSame(CredentialReader::STATUS_VALID, $result['tx_c2pareader_status']);
        self::assertSame('', $result['tx_c2pareader_issues']);
    }

    #[Test]
    public function signerOnTheTrustListGivesTrusted(): void
    {
        $result = $this->reader(withTrustLists: true)->read($this->file(self::FIXTURES . 'ai/chatgpt-gpt-image.png', 'image/png'));

        self::assertSame(CredentialReader::STATUS_TRUSTED, $result['tx_c2pareader_status']);
        self::assertSame(1, $result['tx_c2pareader_ai']);
    }

    #[Test]
    public function signerNotOnTheTrustListStaysValid(): void
    {
        // The c2pa-rs test certificate is on no trust list.
        $result = $this->reader(withTrustLists: true)->read($this->file(self::FIXTURES . 'formats/ai.webp', 'image/webp'));

        self::assertSame(CredentialReader::STATUS_VALID, $result['tx_c2pareader_status']);
    }

    #[Test]
    public function resultContainsExactlyTheProtectedFields(): void
    {
        $result = $this->reader()->read($this->file(self::FIXTURES . 'c2pa-rs/C.jpg', 'image/jpeg'));

        self::assertSame(CredentialReader::FIELDS, array_keys($result));
    }

    #[Test]
    public function unsupportedMimeTypeIsNotSupportedAndUnreadable(): void
    {
        $file = $this->file(self::FIXTURES . 'c2pa-rs/C.jpg', 'application/pdf');
        $reader = $this->reader();

        self::assertFalse($reader->supports($file));
        self::assertSame(CredentialReader::STATUS_UNREADABLE, $reader->read($file)['tx_c2pareader_status']);
    }

    #[Test]
    public function fileAboveTheConfiguredSizeLimitIsNotRead(): void
    {
        $file = $this->createMock(FileInterface::class);
        $file->method('getMimeType')->willReturn('video/mp4');
        $file->method('getSize')->willReturn(2 * 1024 * 1024 + 1);
        $file->expects(self::never())->method('getForLocalProcessing');
        $reader = $this->reader(maxFileSizeMb: 2);

        self::assertFalse($reader->supports($file));
        self::assertSame(CredentialReader::STATUS_UNREADABLE, $reader->read($file)['tx_c2pareader_status']);
    }

    #[Test]
    public function aiFlagIsNotSetWhenSignatureIsInvalid(): void
    {
        // The ChatGPT image with one changed byte in the image data: manifest still there, hash no longer matches.
        $bytes = (string)file_get_contents(self::FIXTURES . 'ai/chatgpt-gpt-image.png');
        $offset = strlen($bytes) - 64;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0xFF);
        $tampered = tempnam(sys_get_temp_dir(), 'c2pa-test');
        file_put_contents($tampered, $bytes);

        try {
            $result = $this->reader()->read($this->file($tampered, 'image/png'));
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

        $result = $this->reader(logger: $logger)->read($missing);

        self::assertSame(CredentialReader::STATUS_UNREADABLE, $result['tx_c2pareader_status']);
    }
}
