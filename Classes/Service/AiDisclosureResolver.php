<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Service;

use Dkd\C2paReader\Domain\AiDisclosure;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Decides whether an AI notice is shown for a file.
 *
 * The Content Credentials claim takes precedence over the editors' flag because it is
 * signed. tx_c2pareader_ai is only set when the signature is valid (see
 * CredentialReader). Works with File and FileReference: TYPO3 passes the metadata
 * through getProperty().
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
                $this->property($file, 'tx_c2pareader_status') === CredentialReader::STATUS_TRUSTED,
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
