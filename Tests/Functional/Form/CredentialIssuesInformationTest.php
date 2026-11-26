<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Functional\Form;

use Dkd\C2paReader\Form\CredentialIssuesInformation;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class CredentialIssuesInformationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['dkd/c2pa-reader'];

    private function render(array $databaseRow, string $language = 'default'): string
    {
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->create($language);
        $node = $this->get(CredentialIssuesInformation::class);
        $node->setData(['databaseRow' => $databaseRow]);

        return $node->render()['html'];
    }

    #[Test]
    public function invalidStatusIsExplained(): void
    {
        $html = $this->render([
            'tx_c2pareader_status' => ['invalid'],
            'tx_c2pareader_issues' => 'assertion.dataHash.mismatch,signingCredential.expired',
        ]);

        self::assertStringContainsString('callout-danger', $html);
        self::assertStringContainsString('The Content Credentials of this file are invalid', $html);
        self::assertStringContainsString('<li>The file was changed after it was signed.</li>', $html);
        self::assertStringContainsString('signing certificate is not valid', $html);
    }

    #[Test]
    public function germanBackendUserGetsGermanText(): void
    {
        $html = $this->render(['tx_c2pareader_status' => ['invalid'], 'tx_c2pareader_issues' => 'assertion.dataHash.mismatch'], 'de');

        self::assertStringContainsString('Die Datei wurde nach dem Signieren verändert.', $html);
    }

    #[Test]
    public function otherStatusesRenderNothing(): void
    {
        foreach (['trusted', 'valid', 'none', 'unreadable', ''] as $status) {
            self::assertSame('', $this->render(['tx_c2pareader_status' => [$status], 'tx_c2pareader_issues' => '']), $status);
        }
    }
}
