<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Hooks;

use Dkd\C2paReader\Service\CredentialReader;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Verhindert, dass die C2PA-Felder über DataHandler geschrieben werden.
 *
 * `readOnly` im TCA wirkt nur in der Anzeige: ein Redakteur mit Schreibrecht auf
 * sys_file_metadata könnte sonst per manipuliertem Formular-POST, Import oder
 * Workspace-Datensatz z. B. "invalid" zu "valid" machen. Die Felder schreibt
 * ausschließlich die Extension selbst über MetaDataRepository (kein DataHandler).
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
