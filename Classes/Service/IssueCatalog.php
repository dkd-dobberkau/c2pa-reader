<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Service;

use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Turns the C2PA status codes in tx_c2pareader_issues into a few sentences
 * an editor understands. Codes without a sentence of their own count as
 * "the manifest is damaged or incomplete".
 */
final class IssueCatalog
{
    public const CHANGED = 'changed';
    public const SIGNATURE = 'signature';
    public const CERTIFICATE = 'certificate';
    public const DAMAGED = 'damaged';

    private const CATEGORIES = [
        'assertion.dataHash.mismatch' => self::CHANGED,
        'assertion.bmffHash.mismatch' => self::CHANGED,
        'assertion.boxesHash.mismatch' => self::CHANGED,
        'assertion.alternativeContentRepresentation.hashMismatch' => self::CHANGED,
        'claimSignature.mismatch' => self::SIGNATURE,
        'claimSignature.missing' => self::SIGNATURE,
        'assertion.hashedURI.mismatch' => self::SIGNATURE,
        'hashedURI.missing' => self::SIGNATURE,
        'signingCredential.invalid' => self::CERTIFICATE,
        'signingCredential.expired' => self::CERTIFICATE,
        'signingCredential.ocsp.revoked' => self::CERTIFICATE,
        'timeStamp.outsideValidity' => self::CERTIFICATE,
        'timeStamp.untrusted' => self::CERTIFICATE,
    ];

    private const LABELS = 'LLL:EXT:c2pa_reader/Resources/Private/Language/locallang_db.xlf:issue.';

    /** @return list<string> the codes stored in tx_c2pareader_issues */
    public function codes(string $issues): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $issues))));
    }

    /**
     * The categories of the codes, most important first, without duplicates.
     *
     * @return list<string>
     */
    public function categories(string $issues): array
    {
        $found = [];
        foreach ($this->codes($issues) as $code) {
            $found[self::CATEGORIES[$code] ?? self::DAMAGED] = true;
        }
        $order = [self::CHANGED, self::SIGNATURE, self::CERTIFICATE, self::DAMAGED];

        return array_values(array_filter($order, static fn(string $category): bool => isset($found[$category])));
    }

    /**
     * One translated sentence per category; plain text, escape it before output.
     *
     * @return list<string>
     */
    public function sentences(string $issues, LanguageService $languageService): array
    {
        $categories = $this->categories($issues) ?: [self::DAMAGED];

        return array_map(
            static fn(string $category): string => $languageService->sL(self::LABELS . $category),
            $categories,
        );
    }
}
