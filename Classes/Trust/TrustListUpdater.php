<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Trust;

use Dkd\C2paReader\Configuration\ExtensionSettings;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Downloads the trust lists of the C2PA conformance programme into the trust
 * directory. Runs only when an administrator calls c2pa:trust:update; nothing
 * in the upload path touches the network.
 *
 * Everything is validated before anything is written: the lists must load as
 * trust settings, and the optional DigiCert root must match its published
 * fingerprint. Files are replaced atomically.
 */
final class TrustListUpdater
{
    private const LIST_BASE_URL = 'https://raw.githubusercontent.com/c2pa-org/conformance-public/main/trust-list/';
    private const DIGICERT_URL = 'https://cacerts.digicert.com/DigiCertTrustedRootG4.crt.pem';
    /** SHA-256 fingerprint of DigiCert Trusted Root G4, as documented by provemark/c2pa-verifier. */
    public const DIGICERT_SHA256 = '552f7bdcf1a7af9e6ce672017f4f12abf77240c78e761ac203d1d9d20ac89988';
    public const METADATA_FILE = 'trust-lists.json';
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly ExtensionSettings $extensionSettings,
    ) {}

    /**
     * @return array{directory: string, files: list<array{file: string, url: string, sha256: string}>}
     */
    public function update(bool $withDigiCert): array
    {
        $files = [
            TrustSettingsProvider::SIGNER_LIST => self::LIST_BASE_URL . TrustSettingsProvider::SIGNER_LIST,
            TrustSettingsProvider::TSA_LIST => self::LIST_BASE_URL . TrustSettingsProvider::TSA_LIST,
        ];
        if ($withDigiCert) {
            $files[TrustSettingsProvider::EXTRA_TSA_ANCHOR] = self::DIGICERT_URL;
        }

        $contents = [];
        foreach ($files as $file => $url) {
            $contents[$file] = $this->download($url);
        }
        $this->validate($contents);

        $directory = $this->extensionSettings->trustDirectory();
        $this->ensureDirectory($directory);
        $sources = [];
        foreach ($files as $file => $url) {
            $this->writeAtomically($directory . '/' . $file, $contents[$file]);
            $sources[] = ['file' => $file, 'url' => $url, 'sha256' => hash('sha256', $contents[$file])];
        }
        if (!$withDigiCert && is_file($directory . '/' . TrustSettingsProvider::EXTRA_TSA_ANCHOR)) {
            unlink($directory . '/' . TrustSettingsProvider::EXTRA_TSA_ANCHOR);
        }
        $this->writeAtomically($directory . '/' . self::METADATA_FILE, json_encode([
            'updated' => gmdate('Y-m-d\TH:i:s\Z'),
            'license' => 'C2PA trust lists: CC BY 4.0, Coalition for Content Provenance and Authenticity (c2pa-org/conformance-public)',
            'sources' => $sources,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

        return ['directory' => $directory, 'files' => $sources];
    }

    /** @param array<string, string> $contents */
    public function validate(array $contents): void
    {
        $digiCert = $contents[TrustSettingsProvider::EXTRA_TSA_ANCHOR] ?? null;
        if ($digiCert !== null) {
            $certificate = openssl_x509_read($digiCert);
            $fingerprint = $certificate !== false ? openssl_x509_fingerprint($certificate, 'sha256') : false;
            if ($fingerprint !== self::DIGICERT_SHA256) {
                throw new \RuntimeException('The DigiCert Trusted Root G4 certificate does not match its published SHA-256 fingerprint.', 1795700001);
            }
        }
        // Throws a TrustException with the reason when a list is not usable.
        TrustSettingsProvider::fromLists(
            $contents[TrustSettingsProvider::SIGNER_LIST],
            $contents[TrustSettingsProvider::TSA_LIST],
            $digiCert,
        );
    }

    private function download(string $url): string
    {
        $response = $this->requestFactory->request($url, 'GET', ['timeout' => 20, 'allow_redirects' => false]);
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Download of %s failed: HTTP %d', $url, $response->getStatusCode()), 1795700002);
        }
        $body = (string)$response->getBody();
        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            throw new \RuntimeException(sprintf('Download of %s returned %d bytes, which is not plausible.', $url, strlen($body)), 1795700003);
        }

        return $body;
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create the trust directory ' . $directory, 1795700004);
        }
    }

    private function writeAtomically(string $path, string $content): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temporary, $content) === false || !rename($temporary, $path)) {
            @unlink($temporary);
            throw new \RuntimeException('Cannot write ' . $path, 1795700005);
        }
    }
}
