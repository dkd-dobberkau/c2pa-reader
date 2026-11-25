<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;

/**
 * The extension settings (ext_conf_template.txt), with their defaults.
 */
final readonly class ExtensionSettings
{
    public const DEFAULT_TRUST_DIRECTORY = 'var/c2pa';
    public const DEFAULT_MAX_FILE_SIZE_MB = 50;

    public function __construct(
        private string $trustDirectory = self::DEFAULT_TRUST_DIRECTORY,
        private int $maxFileSizeMb = self::DEFAULT_MAX_FILE_SIZE_MB,
    ) {}

    public static function fromExtensionConfiguration(ExtensionConfiguration $configuration): self
    {
        try {
            $values = (array)$configuration->get('c2pa_reader');
        } catch (\Throwable) {
            // Not configured yet (e.g. right after installation): use the defaults.
            $values = [];
        }
        $trustDirectory = trim((string)($values['trustDirectory'] ?? ''));
        $maxFileSizeMb = (int)($values['maxFileSizeMb'] ?? 0);

        return new self(
            $trustDirectory !== '' ? $trustDirectory : self::DEFAULT_TRUST_DIRECTORY,
            $maxFileSizeMb > 0 ? $maxFileSizeMb : self::DEFAULT_MAX_FILE_SIZE_MB,
        );
    }

    /** Absolute path of the trust directory, without a trailing slash. */
    public function trustDirectory(): string
    {
        $directory = rtrim($this->trustDirectory, '/');

        return str_starts_with($directory, '/') ? $directory : Environment::getProjectPath() . '/' . $directory;
    }

    public function maxFileSizeBytes(): int
    {
        return $this->maxFileSizeMb * 1024 * 1024;
    }
}
