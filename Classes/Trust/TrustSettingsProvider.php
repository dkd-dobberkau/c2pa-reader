<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Trust;

use Dkd\C2paReader\Configuration\ExtensionSettings;
use Provemark\C2paVerifier\Trust\TrustSettings;
use Psr\Log\LoggerInterface;

/**
 * Loads the trust settings from the trust directory, once per process.
 *
 * Order: a hand-made trust.settings.json (c2patool format) wins; otherwise the
 * lists that c2pa:trust:update downloaded. Without either, there is no trust
 * check and the best status is "valid". A broken file is logged and treated
 * like a missing one, so uploads never fail because of it.
 */
final class TrustSettingsProvider
{
    public const SETTINGS_FILE = 'trust.settings.json';
    public const SIGNER_LIST = 'C2PA-TRUST-LIST.json';
    public const TSA_LIST = 'C2PA-TSA-TRUST-LIST.pem';
    public const EXTRA_TSA_ANCHOR = 'DigiCertTrustedRootG4.crt.pem';

    private bool $loaded = false;
    private ?TrustSettings $settings = null;

    public function __construct(
        private readonly ExtensionSettings $extensionSettings,
        private readonly LoggerInterface $logger,
    ) {}

    public function get(): ?TrustSettings
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $this->settings = $this->load($this->extensionSettings->trustDirectory());
        }

        return $this->settings;
    }

    private function load(string $directory): ?TrustSettings
    {
        try {
            if (is_file($directory . '/' . self::SETTINGS_FILE)) {
                return TrustSettings::fromJson($this->read($directory . '/' . self::SETTINGS_FILE));
            }
            if (!is_file($directory . '/' . self::SIGNER_LIST)) {
                return null;
            }

            return self::fromLists(
                $this->read($directory . '/' . self::SIGNER_LIST),
                is_file($directory . '/' . self::TSA_LIST) ? $this->read($directory . '/' . self::TSA_LIST) : null,
                is_file($directory . '/' . self::EXTRA_TSA_ANCHOR) ? $this->read($directory . '/' . self::EXTRA_TSA_ANCHOR) : null,
            );
        } catch (\Throwable $e) {
            $this->logger->error('C2PA trust settings in {directory} could not be loaded; checking without trust: {message}', [
                'directory' => $directory,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * The signer list anchors signers only, the TSA list (plus an optional extra
     * TSA root) anchors timestamp authorities only (C2PA 2.4 §14.4).
     */
    public static function fromLists(string $signerList, ?string $tsaList, ?string $extraTsaAnchor = null): TrustSettings
    {
        $tsas = $tsaList;
        if ($extraTsaAnchor !== null) {
            $tsas = rtrim((string)$tsaList) . "\n" . $extraTsaAnchor;
        }

        return TrustSettings::fromTrustLists($signerList, $tsas);
    }

    private function read(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Cannot read ' . $path);
        }

        return $content;
    }
}
