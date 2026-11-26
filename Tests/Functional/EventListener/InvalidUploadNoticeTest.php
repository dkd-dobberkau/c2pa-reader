<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Tests\Functional\EventListener;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * An invalid file uploaded in the backend is reported to the editor right away.
 */
final class InvalidUploadNoticeTest extends FunctionalTestCase
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/';

    protected array $testExtensionsToLoad = ['dkd/c2pa-reader'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/sys_file.csv');
        @mkdir($this->instancePath . '/fileadmin', 0777, true);
    }

    private function upload(string $fixture, bool $backend): array
    {
        if ($backend) {
            $this->setUpBackendUser(1);
            $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.org/typo3/'))
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        }
        $storage = $this->get(StorageRepository::class)->findByUid(1);
        $tmp = $this->instancePath . '/typo3temp/' . uniqid('c2pa', true);
        copy(self::FIXTURES . $fixture, $tmp);
        $storage->addFile($tmp, $storage->getRootLevelFolder(), basename($fixture), DuplicationBehavior::REPLACE);

        return $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessagesAndFlush();
    }

    #[Test]
    public function invalidFileInTheBackendQueuesAWarningWithTheReason(): void
    {
        $messages = $this->upload('c2pa-rs/E-sig-CA.jpg', true);

        self::assertCount(1, $messages);
        self::assertSame(ContextualFeedbackSeverity::WARNING, $messages[0]->getSeverity());
        self::assertSame('Content Credentials invalid', $messages[0]->getTitle());
        self::assertSame(
            'E-sig-CA.jpg: The signature does not match the manifest, or the manifest was altered.',
            $messages[0]->getMessage(),
        );
    }

    #[Test]
    public function validFileQueuesNothing(): void
    {
        self::assertSame([], $this->upload('c2pa-rs/C.jpg', true));
    }

    #[Test]
    public function invalidFileOutsideTheBackendQueuesNothing(): void
    {
        // CLI, scheduler, frontend: nobody to show it to.
        unset($GLOBALS['TYPO3_REQUEST']);
        self::assertSame([], $this->upload('c2pa-rs/E-sig-CA.jpg', false));
    }
}
