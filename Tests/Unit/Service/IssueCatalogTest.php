<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Unit\Service;

use Dkd\C2paReader\Service\IssueCatalog;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class IssueCatalogTest extends UnitTestCase
{
    #[Test]
    public function codesAreGroupedMostImportantFirstWithoutDuplicates(): void
    {
        $categories = (new IssueCatalog())->categories(
            'signingCredential.expired, assertion.dataHash.mismatch,claimSignature.mismatch,assertion.bmffHash.mismatch'
        );

        self::assertSame([IssueCatalog::CHANGED, IssueCatalog::SIGNATURE, IssueCatalog::CERTIFICATE], $categories);
    }

    #[Test]
    public function unknownCodesCountAsDamagedManifest(): void
    {
        self::assertSame([IssueCatalog::DAMAGED], (new IssueCatalog())->categories('claim.cbor.invalid,assertion.missing'));
    }

    #[Test]
    public function emptyIssuesHaveNoCategory(): void
    {
        self::assertSame([], (new IssueCatalog())->categories(''));
        self::assertSame([], (new IssueCatalog())->codes(' , '));
    }

    #[Test]
    public function sentencesAreTranslatedPerCategory(): void
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('sL')->willReturnCallback(static fn(string $key): string => 'T:' . substr($key, strrpos($key, ':') + 1));

        self::assertSame(
            ['T:issue.changed', 'T:issue.certificate'],
            (new IssueCatalog())->sentences('signingCredential.invalid,assertion.dataHash.mismatch', $languageService),
        );
    }

    #[Test]
    public function invalidWithoutRecordedCodesStillGetsASentence(): void
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('sL')->willReturnArgument(0);

        self::assertSame(
            ['LLL:EXT:c2pa_reader/Resources/Private/Language/locallang_db.xlf:issue.damaged'],
            (new IssueCatalog())->sentences('', $languageService),
        );
    }
}
