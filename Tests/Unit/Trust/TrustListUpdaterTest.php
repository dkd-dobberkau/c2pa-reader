<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Unit\Trust;

use Dkd\C2paReader\Configuration\ExtensionSettings;
use Dkd\C2paReader\Trust\TrustListUpdater;
use Dkd\C2paReader\Trust\TrustSettingsProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * No network: the HTTP client returns the snapshot in Tests/Fixtures/trust-list.
 */
final class TrustListUpdaterTest extends UnitTestCase
{
    private const LISTS = __DIR__ . '/../../Fixtures/trust-list/';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        // A directory that does not exist yet: the updater must create it.
        $this->directory = sys_get_temp_dir() . '/c2pa-trust-update-' . bin2hex(random_bytes(4)) . '/var/c2pa';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            array_map('unlink', glob($this->directory . '/*') ?: []);
            rmdir($this->directory);
            rmdir(dirname($this->directory));
            rmdir(dirname($this->directory, 2));
        }
        parent::tearDown();
    }

    /** @param array<string, string> $overrides file name => body */
    private function updater(array $overrides = [], ?array &$requested = null): TrustListUpdater
    {
        $requested = [];
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(
            static function (string $url) use ($overrides, &$requested): Response {
                $requested[] = $url;
                $file = basename(parse_url($url, PHP_URL_PATH));
                $body = $overrides[$file] ?? (string)file_get_contents(self::LISTS . $file);
                $stream = new Stream('php://temp', 'rw');
                $stream->write($body);
                $stream->rewind();

                return new Response($stream, 200);
            }
        );

        return new TrustListUpdater($requestFactory, new ExtensionSettings($this->directory));
    }

    #[Test]
    public function listsAreSavedWithMetadataAndLoadable(): void
    {
        $result = $this->updater([], $requested)->update(false);

        self::assertCount(2, $requested);
        self::assertStringStartsWith('https://raw.githubusercontent.com/c2pa-org/conformance-public/main/trust-list/', $requested[0]);
        self::assertFileEquals(self::LISTS . TrustSettingsProvider::SIGNER_LIST, $this->directory . '/' . TrustSettingsProvider::SIGNER_LIST);
        self::assertFileEquals(self::LISTS . TrustSettingsProvider::TSA_LIST, $this->directory . '/' . TrustSettingsProvider::TSA_LIST);
        self::assertFileDoesNotExist($this->directory . '/' . TrustSettingsProvider::EXTRA_TSA_ANCHOR);

        $metadata = json_decode((string)file_get_contents($this->directory . '/' . TrustListUpdater::METADATA_FILE), true);
        self::assertCount(2, $metadata['sources']);
        self::assertSame(hash_file('sha256', self::LISTS . TrustSettingsProvider::SIGNER_LIST), $metadata['sources'][0]['sha256']);
        self::assertSame($result['files'], $metadata['sources']);

        $provider = new TrustSettingsProvider(new ExtensionSettings($this->directory), new NullLogger());
        self::assertNotNull($provider->get());
    }

    #[Test]
    public function digiCertRootIsAddedOnRequestAndRemovedWithoutIt(): void
    {
        $this->updater([], $requested)->update(true);
        self::assertCount(3, $requested);
        self::assertSame('https://cacerts.digicert.com/DigiCertTrustedRootG4.crt.pem', $requested[2]);
        self::assertFileExists($this->directory . '/' . TrustSettingsProvider::EXTRA_TSA_ANCHOR);

        $this->updater()->update(false);
        self::assertFileDoesNotExist($this->directory . '/' . TrustSettingsProvider::EXTRA_TSA_ANCHOR);
    }

    #[Test]
    public function digiCertRootWithWrongFingerprintIsRefusedAndNothingIsWritten(): void
    {
        // Any other certificate: one from the TSA list.
        preg_match('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', (string)file_get_contents(self::LISTS . TrustSettingsProvider::TSA_LIST), $other);

        $this->expectExceptionCode(1795700001);
        try {
            $this->updater([TrustSettingsProvider::EXTRA_TSA_ANCHOR => $other[0]])->update(true);
        } finally {
            self::assertDirectoryDoesNotExist($this->directory);
        }
    }

    #[Test]
    public function unusableListIsRefusedAndNothingIsWritten(): void
    {
        $failed = false;
        try {
            $this->updater([TrustSettingsProvider::TSA_LIST => 'no certificate here'])->update(false);
        } catch (\Throwable) {
            $failed = true;
        }

        self::assertTrue($failed);
        self::assertDirectoryDoesNotExist($this->directory);
    }
}
