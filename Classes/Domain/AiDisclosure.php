<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Domain;

/**
 * Ein KI-Hinweis für eine Datei: woher die Angabe stammt und welche Software genannt ist.
 */
final readonly class AiDisclosure
{
    /** Das signierte Manifest der Datei sagt "KI-generiert" (Signatur gültig). */
    public const SOURCE_CREDENTIALS = 'credentials';
    /** Die Redaktion hat die Datei als KI-generiert markiert. */
    public const SOURCE_EDITOR = 'editor';

    public function __construct(
        public string $source,
        public string $agents = '',
    ) {}

    public function isFromCredentials(): bool
    {
        return $this->source === self::SOURCE_CREDENTIALS;
    }
}
