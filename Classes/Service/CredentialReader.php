<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Service;

use Dkd\C2paReader\Configuration\ExtensionSettings;
use Dkd\C2paReader\Trust\TrustSettingsProvider;
use Provemark\C2paVerifier\Report\ValidationState;
use Provemark\C2paVerifier\Verifier\VerificationReport;
use Provemark\C2paVerifier\Verifier\Verifier;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\FileInterface;

/**
 * Reads C2PA Content Credentials from a FAL file and returns the values
 * for the fields in sys_file_metadata.
 *
 * Meaning: "valid" says that signature and hashes match the manifest; the
 * signer is unknown. "trusted" says the same, and the signer's certificate
 * chains to an anchor of the trust lists (see TrustSettingsProvider). Without
 * trust lists, "valid" is the best status.
 */
final class CredentialReader
{
    public const STATUS_NONE = 'none';
    public const STATUS_VALID = 'valid';
    public const STATUS_TRUSTED = 'trusted';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_UNREADABLE = 'unreadable';

    /** The fields in sys_file_metadata that only this class fills. */
    public const FIELDS = [
        'tx_c2pareader_status',
        'tx_c2pareader_ai',
        'tx_c2pareader_agents',
        'tx_c2pareader_checked',
    ];

    /**
     * MIME types that are checked, including the aliases TYPO3 and fileinfo use.
     * The verifier detects the format from the magic bytes itself, so a wrong
     * extension does not matter.
     */
    public const SUPPORTED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/heic', 'image/heif',
        'video/mp4', 'video/quicktime',
        'audio/wav', 'audio/x-wav', 'audio/wave', 'audio/vnd.wave', 'audio/mpeg', 'audio/mp4', 'audio/x-m4a',
    ];

    /** IPTC digitalSourceType values that mean creation by a trained model. */
    private const AI_SOURCE_TYPES = [
        'trainedAlgorithmicMedia',
        'compositeWithTrainedAlgorithmicMedia',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ExtensionSettings $settings,
        private readonly TrustSettingsProvider $trust,
    ) {}

    public function supports(FileInterface $file): bool
    {
        // The check runs during the upload request; the size limit keeps it short.
        return in_array($file->getMimeType(), self::SUPPORTED_MIME_TYPES, true)
            && $file->getSize() <= $this->settings->maxFileSizeBytes();
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
            $report = (new Verifier())->verify($stream, $this->trust->get());

            if (!$report->hasManifest) {
                $result['tx_c2pareader_status'] = self::STATUS_NONE;
                return $result;
            }

            $valid = $report->result->state !== ValidationState::Invalid;
            $activeManifest = $this->activeManifest($report);

            $result['tx_c2pareader_status'] = match ($report->result->state) {
                ValidationState::Trusted => self::STATUS_TRUSTED,
                ValidationState::Valid => self::STATUS_VALID,
                ValidationState::Invalid => self::STATUS_INVALID,
            };
            // The AI flag is only a claim made by the manifest.
            // It is only taken over when the signature is valid.
            $result['tx_c2pareader_ai'] = ($valid && $this->isAiGenerated($activeManifest)) ? 1 : 0;
            $result['tx_c2pareader_agents'] = mb_substr(implode(', ', $this->softwareAgents($activeManifest)), 0, 255);
        } catch (\Throwable $e) {
            $this->logger->warning('C2PA read failed for file {uid} ({identifier}): {message}', [
                // FileInterface has no getUid(); File and FileReference provide the uid as a property.
                'uid' => $file->hasProperty('uid') ? $file->getProperty('uid') : null,
                'identifier' => $file->getIdentifier(),
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
     * The active manifest in the shape c2patool prints (claim_generator_info, assertions ...).
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
            // The value is an IPTC URI, e.g. http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia
            if (in_array(basename($sourceType), self::AI_SOURCE_TYPES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Claim generator and the softwareAgents of the actions, without duplicates.
     *
     * @param array<string, mixed> $manifest
     * @return list<string>
     */
    private function softwareAgents(array $manifest): array
    {
        $agents = [];
        $generators = $manifest['claim_generator_info'] ?? [];
        // Claim v2 has a single object, v1 a list.
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

    /** Depending on the claim version, softwareAgent is a string or an object {name, version}. */
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
