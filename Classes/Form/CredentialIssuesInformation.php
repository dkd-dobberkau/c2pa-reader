<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Form;

use Dkd\C2paReader\Service\CredentialReader;
use Dkd\C2paReader\Service\IssueCatalog;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * Field information for tx_c2pareader_status: when the Content Credentials are
 * invalid, says why in words, above the read-only status.
 */
final class CredentialIssuesInformation extends AbstractNode
{
    private const LABELS = 'LLL:EXT:c2pa_reader/Resources/Private/Language/locallang_db.xlf:';

    public function __construct(private readonly IssueCatalog $issueCatalog) {}

    public function render(): array
    {
        $resultArray = $this->initializeResultArray();
        $row = $this->data['databaseRow'] ?? [];
        // A select field arrives as a list of values in FormEngine.
        $status = $row['tx_c2pareader_status'] ?? '';
        $status = is_array($status) ? (string)reset($status) : (string)$status;
        if ($status !== CredentialReader::STATUS_INVALID) {
            return $resultArray;
        }

        $languageService = $this->getLanguageService();
        $items = '';
        foreach ($this->issueCatalog->sentences((string)($row['tx_c2pareader_issues'] ?? ''), $languageService) as $sentence) {
            $items .= '<li>' . htmlspecialchars($sentence) . '</li>';
        }
        $resultArray['html'] = '<div class="callout callout-danger mb-2" role="alert">'
            . '<div class="callout-title">' . htmlspecialchars($languageService->sL(self::LABELS . 'issues.title')) . '</div>'
            . '<div class="callout-body"><ul class="mb-1">' . $items . '</ul>'
            . '<p class="mb-0">' . htmlspecialchars($languageService->sL(self::LABELS . 'issues.hint')) . '</p></div>'
            . '</div>';

        return $resultArray;
    }

    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
