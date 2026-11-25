<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Hooks;

use Dkd\C2paReader\Service\CredentialReader;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Prevents the C2PA fields from being written through DataHandler.
 *
 * `readOnly` in TCA only affects the display: otherwise an editor with write access to
 * sys_file_metadata could turn "invalid" into "valid" with a manipulated form POST, an
 * import or a workspace record. Only the extension itself writes these fields, through
 * MetaDataRepository (not DataHandler).
 */
final class ProtectCredentialFields
{
    private const TABLE = 'sys_file_metadata';

    /**
     * @param array<string, mixed> $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(
        array &$incomingFieldArray,
        string $table,
        int|string $id,
        DataHandler $dataHandler,
    ): void {
        if ($table !== self::TABLE) {
            return;
        }
        foreach (CredentialReader::FIELDS as $field) {
            unset($incomingFieldArray[$field]);
        }
    }
}
