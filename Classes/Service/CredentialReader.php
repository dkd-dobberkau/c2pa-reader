<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Service;

use Provemark\C2paVerifier\Report\ValidationState;
use Provemark\C2paVerifier\Verifier\VerificationReport;
use Provemark\C2paVerifier\Verifier\Verifier;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Liest C2PA Content Credentials aus einer FAL-Datei und liefert die Werte
 * für die Felder in sys_file_metadata.
 *
 * Aussagekraft: "valid" heißt nur, dass Signatur und Hashes zum Manifest passen.
 * Ob der Aussteller vertrauenswürdig ist (Trust List), wird hier NICHT geprüft:
 * der Verifier läuft ohne TrustSettings und liefert dann höchstens "Valid".
 */
final class CredentialReader
{
    public const STATUS_NONE = 'none';
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_UNREADABLE = 'unreadable';

    /** Die Felder in sys_file_metadata, die nur diese Klasse befüllt. */
    public const FIELDS = [
        'tx_c2pareader_status',
        'tx_c2pareader_ai',
        'tx_c2pareader_agents',
        'tx_c2pareader_checked',
    ];

    /**
     * MIME-Typen, die geprüft werden. Der Verifier erkennt das Format selbst an den
     * Magic Bytes und kann deutlich mehr (WebP, GIF, AVIF/HEIC, MP4, WAV, MP3 ...);
     * die Liste begrenzt bewusst, was der PoC anfasst.
     */
    public const SUPPORTED_MIME_TYPES = ['image/jpeg', 'image/png'];

    /** IPTC digitalSourceType-Werte, die eine Erzeugung durch ein trainiertes Modell bedeuten. */
    private const AI_SOURCE_TYPES = [
        'trainedAlgorithmicMedia',
        'compositeWithTrainedAlgorithmicMedia',
    ];

    /** Obergrenze, damit kein Upload den PHP-Speicher sprengt (Bytes). */
    private const MAX_BYTES = 50 * 1024 * 1024;

    public function __construct(private readonly LoggerInterface $logger) {}

    public function supports(FileInterface $file): bool
    {
        return in_array($file->getMimeType(), self::SUPPORTED_MIME_TYPES, true)
            && $file->getSize() <= self::MAX_BYTES;
    }

    /**
     * @return array{tx_c2pareader_status: string, tx_c2pareader_ai: int, tx_c2pareader_agents: string, tx_c2pareader_checked: int}
     */
    public function read(FileInterface $file): array
    {
        $result = [
            'tx_c2pareader_status' => self::STATUS_UNREADABLE,
            'tx_c2pareader_ai' => 0,
            'tx_c2pareader_agents' => '',
            'tx_c2pareader_checked' => time(),
        ];

        if (!$this->supports($file)) {
            return $result;
        }

        $stream = null;
        try {
            $stream = fopen($file->getForLocalProcessing(false), 'rb');
            if ($stream === false) {
                throw new \RuntimeException('File could not be opened for reading.');
            }
            $report = (new Verifier())->verify($stream);

            if (!$report->hasManifest) {
                $result['tx_c2pareader_status'] = self::STATUS_NONE;
                return $result;
            }

            $valid = $report->result->state !== ValidationState::Invalid;
            $activeManifest = $this->activeManifest($report);

            $result['tx_c2pareader_status'] = $valid ? self::STATUS_VALID : self::STATUS_INVALID;
            // Die KI-Markierung ist nur eine Behauptung des Manifests.
            // Sie wird nur bei gültiger Signatur übernommen.
            $result['tx_c2pareader_ai'] = ($valid && $this->isAiGenerated($activeManifest)) ? 1 : 0;
            $result['tx_c2pareader_agents'] = mb_substr(implode(', ', $this->softwareAgents($activeManifest)), 0, 255);
        } catch (\Throwable $e) {
            $this->logger->warning('C2PA read failed for file {uid}: {message}', [
                'uid' => $file->getUid(),
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $result;
    }

    /**
     * Das aktive Manifest in der Form, die c2patool ausgibt (claim_generator_info, assertions ...).
     *
     * @return array<string, mixed>
     */
    private function activeManifest(VerificationReport $report): array
    {
        $store = $report->toArray();
        $label = $store['active_manifest'] ?? null;
        $manifest = is_string($label) ? ($store['manifests'][$label] ?? null) : null;

        return is_array($manifest) ? $manifest : [];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<array<string, mixed>>
     */
    private function actions(array $manifest): array
    {
        $actions = [];
        foreach ($manifest['assertions'] ?? [] as $assertion) {
            if (!str_starts_with((string)($assertion['label'] ?? ''), 'c2pa.actions')) {
                continue;
            }
            foreach ($assertion['data']['actions'] ?? [] as $action) {
                if (is_array($action)) {
                    $actions[] = $action;
                }
            }
        }

        return $actions;
    }

    /** @param array<string, mixed> $manifest */
    private function isAiGenerated(array $manifest): bool
    {
        foreach ($this->actions($manifest) as $action) {
            $sourceType = (string)($action['digitalSourceType'] ?? '');
            // Wert ist eine IPTC-URI, z. B. http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia
            if (in_array(basename($sourceType), self::AI_SOURCE_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Claim Generator und die softwareAgents der Actions, ohne Dubletten.
     *
     * @param array<string, mixed> $manifest
     * @return list<string>
     */
    private function softwareAgents(array $manifest): array
    {
        $agents = [];
        $generators = $manifest['claim_generator_info'] ?? [];
        // Claim v2 hat ein einzelnes Objekt, v1 eine Liste.
        if (is_array($generators) && isset($generators['name'])) {
            $generators = [$generators];
        }
        foreach (is_array($generators) ? $generators : [] as $generator) {
            $agents[] = $this->agentName($generator);
        }
        if ($agents === [] && is_string($manifest['claim_generator'] ?? null)) {
            $agents[] = $manifest['claim_generator'];
        }
        foreach ($this->actions($manifest) as $action) {
            $agents[] = $this->agentName($action['softwareAgent'] ?? null);
        }

        return array_values(array_unique(array_filter($agents, static fn(string $agent): bool => $agent !== '')));
    }

    /** softwareAgent ist je nach Claim-Version ein String oder ein Objekt {name, version}. */
    private function agentName(mixed $agent): string
    {
        if (is_string($agent)) {
            return trim($agent);
        }
        if (is_array($agent) && is_string($agent['name'] ?? null)) {
            return trim($agent['name'] . ' ' . (is_scalar($agent['version'] ?? null) ? (string)$agent['version'] : ''));
        }

        return '';
    }
}
