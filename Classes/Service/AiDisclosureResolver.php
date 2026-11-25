<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Service;

use Dkd\C2paReader\Domain\AiDisclosure;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Entscheidet, ob für eine Datei ein KI-Hinweis angezeigt wird.
 *
 * Die Angabe aus den Content Credentials hat Vorrang vor der Angabe der Redaktion,
 * weil sie signiert ist. tx_c2pareader_ai ist nur bei gültiger Signatur gesetzt
 * (siehe CredentialReader). Funktioniert mit File und FileReference: TYPO3 reicht
 * die Metadaten über getProperty() durch.
 */
final class AiDisclosureResolver
{
    public const FIELD_MANUAL = 'tx_c2pareader_ai_manual';

    public function resolve(FileInterface $file): ?AiDisclosure
    {
        if ($this->flag($file, 'tx_c2pareader_ai')) {
            return new AiDisclosure(
                AiDisclosure::SOURCE_CREDENTIALS,
                (string)($this->property($file, 'tx_c2pareader_agents') ?? ''),
            );
        }
        if ($this->flag($file, self::FIELD_MANUAL)) {
            return new AiDisclosure(AiDisclosure::SOURCE_EDITOR);
        }

        return null;
    }

    private function flag(FileInterface $file, string $field): bool
    {
        return (int)($this->property($file, $field) ?? 0) === 1;
    }

    private function property(FileInterface $file, string $field): mixed
    {
        return $file->hasProperty($field) ? $file->getProperty($field) : null;
    }
}
