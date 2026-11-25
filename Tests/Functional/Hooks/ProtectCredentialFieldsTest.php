<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Der echte DataHandler darf die Prüf-Felder nicht schreiben, auch nicht als Admin.
 * Regressionstest für den Befund aus SECURITY.md.
 */
final class ProtectCredentialFieldsTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['dkd/c2pa-reader'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sys_file.csv');
        @mkdir($this->instancePath . '/fileadmin', 0777, true);
        copy(__DIR__ . '/../../Fixtures/c2pa-rs/E-sig-CA.jpg', $this->instancePath . '/fileadmin/example.jpg');
    }

    /** @return iterable<string, array{int}> */
    public static function userProvider(): iterable
    {
        yield 'admin' => [1];
        yield 'editor' => [2];
    }

    #[Test]
    #[DataProvider('userProvider')]
    public function credentialFieldsCannotBeForgedButOtherFieldsCanBeEdited(int $userUid): void
    {
        // Redakteur: Gruppe mit Schreibrecht auf sys_file_metadata, Freigabe für title und das
        // manuelle KI-Feld und einem beschreibbaren File Mount (siehe be_users.csv).
        $backendUser = $this->setUpBackendUser($userUid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        $dataHandler = $this->get(DataHandler::class);
        $dataHandler->start(['sys_file_metadata' => [1 => [
            'title' => 'edited',
            'tx_c2pareader_status' => 'valid',
            'tx_c2pareader_ai' => 1,
            'tx_c2pareader_agents' => 'Forged Camera 1.0',
            'tx_c2pareader_checked' => 1,
            'tx_c2pareader_ai_manual' => 1,
        ]]], [], $backendUser);
        $dataHandler->process_datamap();

        $row = $this->get(ConnectionPool::class)->getConnectionForTable('sys_file_metadata')
            ->select(['*'], 'sys_file_metadata', ['uid' => 1])->fetchAssociative();

        self::assertSame([], $dataHandler->errorLog);
        self::assertSame('edited', $row['title']);
        self::assertSame(1, (int)$row['tx_c2pareader_ai_manual']);
        self::assertSame('invalid', $row['tx_c2pareader_status']);
        self::assertSame(0, (int)$row['tx_c2pareader_ai']);
        self::assertSame('make_test_images 0.33.1', $row['tx_c2pareader_agents']);
        self::assertSame(1700000000, (int)$row['tx_c2pareader_checked']);
    }
}
