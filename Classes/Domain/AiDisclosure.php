<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Domain;

/**
 * An AI notice for a file: where the claim comes from and which software is named.
 */
final readonly class AiDisclosure
{
    /** The signed manifest of the file says "AI-generated" (signature valid). */
    public const SOURCE_CREDENTIALS = 'credentials';
    /** An editor has marked the file as AI-generated. */
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
