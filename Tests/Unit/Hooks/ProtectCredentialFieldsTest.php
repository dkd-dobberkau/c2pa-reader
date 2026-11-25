<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Unit\Hooks;

use Dkd\C2paReader\Hooks\ProtectCredentialFields;
use Dkd\C2paReader\Service\CredentialReader;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProtectCredentialFieldsTest extends UnitTestCase
{
    /** @return array<string, mixed> */
    private static function incoming(): array
    {
        return [
            'title' => 'changed',
            'tx_c2pareader_status' => 'valid',
            'tx_c2pareader_ai' => 0,
            'tx_c2pareader_agents' => 'Forged Camera 1.0',
            'tx_c2pareader_checked' => 1,
            'tx_c2pareader_ai_manual' => 1,
        ];
    }

    #[Test]
    public function credentialFieldsAreRemovedForFileMetadata(): void
    {
        $fields = self::incoming();
        (new ProtectCredentialFields())->processDatamap_preProcessFieldArray($fields, 'sys_file_metadata', 5, $this->createStub(DataHandler::class));

        foreach (CredentialReader::FIELDS as $field) {
            self::assertArrayNotHasKey($field, $fields);
        }
        self::assertSame(['title' => 'changed', 'tx_c2pareader_ai_manual' => 1], $fields);
    }

    #[Test]
    public function newRecordsAreProtectedToo(): void
    {
        $fields = self::incoming();
        (new ProtectCredentialFields())->processDatamap_preProcessFieldArray($fields, 'sys_file_metadata', 'NEW123', $this->createStub(DataHandler::class));

        self::assertArrayNotHasKey('tx_c2pareader_status', $fields);
    }

    #[Test]
    public function otherTablesAreLeftAlone(): void
    {
        $fields = self::incoming();
        (new ProtectCredentialFields())->processDatamap_preProcessFieldArray($fields, 'tt_content', 5, $this->createStub(DataHandler::class));

        self::assertSame(self::incoming(), $fields);
    }
}
