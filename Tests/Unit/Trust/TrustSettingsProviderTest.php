<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Unit\Trust;

use Dkd\C2paReader\Configuration\ExtensionSettings;
use Dkd\C2paReader\Trust\TrustSettingsProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class TrustSettingsProviderTest extends UnitTestCase
{
    private const LISTS = __DIR__ . '/../../Fixtures/trust-list/';

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . '/c2pa-trust-test-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory . '/*') ?: []);
        rmdir($this->directory);
        parent::tearDown();
    }

    private function provider(?LoggerInterface $logger = null): TrustSettingsProvider
    {
        return new TrustSettingsProvider(new ExtensionSettings($this->directory), $logger ?? new NullLogger());
    }

    #[Test]
    public function emptyDirectoryMeansNoTrustCheck(): void
    {
        self::assertNull($this->provider()->get());
    }

    #[Test]
    public function missingDirectoryMeansNoTrustCheck(): void
    {
        $provider = new TrustSettingsProvider(new ExtensionSettings($this->directory . '/missing'), new NullLogger());

        self::assertNull($provider->get());
    }

    #[Test]
    public function downloadedListsAreLoadedWithBothKinds(): void
    {
        copy(self::LISTS . TrustSettingsProvider::SIGNER_LIST, $this->directory . '/' . TrustSettingsProvider::SIGNER_LIST);
        copy(self::LISTS . TrustSettingsProvider::TSA_LIST, $this->directory . '/' . TrustSettingsProvider::TSA_LIST);

        $settings = $this->provider()->get();

        self::assertNotNull($settings);
        self::assertCount(2, $settings->anchorSets);
    }

    #[Test]
    public function handMadeSettingsFileTakesPrecedence(): void
    {
        copy(self::LISTS . TrustSettingsProvider::SIGNER_LIST, $this->directory . '/' . TrustSettingsProvider::SIGNER_LIST);
        file_put_contents($this->directory . '/' . TrustSettingsProvider::SETTINGS_FILE, json_encode([
            'trust' => ['anchors' => [[
                'trust_anchors' => file_get_contents(self::LISTS . TrustSettingsProvider::EXTRA_TSA_ANCHOR),
                'trust_kind' => 'tsa',
            ]]],
        ]));

        $settings = $this->provider()->get();

        self::assertNotNull($settings);
        self::assertCount(1, $settings->anchorSets);
    }

    #[Test]
    public function brokenListIsLoggedAndIgnored(): void
    {
        file_put_contents($this->directory . '/' . TrustSettingsProvider::SIGNER_LIST, '{"not": "a trust list"}');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        self::assertNull($this->provider($logger)->get());
    }

    #[Test]
    public function settingsAreLoadedOnlyOnce(): void
    {
        copy(self::LISTS . TrustSettingsProvider::SIGNER_LIST, $this->directory . '/' . TrustSettingsProvider::SIGNER_LIST);
        $provider = $this->provider();

        self::assertSame($provider->get(), $provider->get());
    }
}
