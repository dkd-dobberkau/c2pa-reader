<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Functional\Command;

use Dkd\C2paReader\Command\TrustUpdateCommand;
use Dkd\C2paReader\Configuration\ExtensionSettings;
use Dkd\C2paReader\Service\CredentialScanner;
use Dkd\C2paReader\Trust\TrustListUpdater;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * c2pa:trust:update with a fake download; --rescan checks the existing files again.
 */
final class TrustUpdateCommandTest extends FunctionalTestCase
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/';

    protected array $testExtensionsToLoad = ['dkd/c2pa-reader'];

    protected function setUp(): void
    {
        parent::setUp();
        // sys_file 1 is /example.jpg with an old check (1700000000).
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sys_file.csv');
        @mkdir($this->instancePath . '/fileadmin', 0777, true);
        copy(self::FIXTURES . 'c2pa-rs/E-sig-CA.jpg', $this->instancePath . '/fileadmin/example.jpg');
    }

    private function tester(): CommandTester
    {
        $requestFactory = $this->createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(static function (string $url): Response {
            $stream = new Stream('php://temp', 'rw');
            $stream->write((string)file_get_contents(self::FIXTURES . 'trust-list/' . basename((string)parse_url($url, PHP_URL_PATH))));
            $stream->rewind();
            return new Response($stream, 200);
        });
        $updater = new TrustListUpdater($requestFactory, new ExtensionSettings($this->instancePath . '/var/c2pa-test'));

        return new CommandTester(new TrustUpdateCommand($updater, $this->get(CredentialScanner::class)));
    }

    /** @return array<string, mixed> */
    private function metadata(): array
    {
        return $this->get(ConnectionPool::class)->getConnectionForTable('sys_file_metadata')
            ->select(['*'], 'sys_file_metadata', ['file' => 1])->fetchAssociative();
    }

    #[Test]
    public function withoutRescanOnlyTheListsAreUpdated(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertFileExists($this->instancePath . '/var/c2pa-test/C2PA-TRUST-LIST.json');
        self::assertStringContainsString('c2pa:scan --force', $tester->getDisplay());
        self::assertSame(1700000000, (int)$this->metadata()['tx_c2pareader_checked']);
    }

    #[Test]
    public function rescanChecksExistingFilesAgain(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--rescan' => true]));
        $metadata = $this->metadata();
        self::assertGreaterThan(1700000000, (int)$metadata['tx_c2pareader_checked']);
        self::assertSame('invalid', $metadata['tx_c2pareader_status']);
        self::assertSame('claimSignature.mismatch', $metadata['tx_c2pareader_issues']);
        self::assertMatchesRegularExpression('/invalid\s+1/', $tester->getDisplay());
    }
}
