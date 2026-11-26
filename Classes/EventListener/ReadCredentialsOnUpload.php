<?php

declare(strict_types=1);

namespace Dkd\C2paReader\EventListener;

use Dkd\C2paReader\Service\CredentialReader;
use Dkd\C2paReader\Service\IssueCatalog;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Checks Content Credentials after a file is uploaded or replaced
 * and writes the result to sys_file_metadata. In the backend, an invalid
 * result is also reported to the editor right away.
 */
final class ReadCredentialsOnUpload
{
    private const LABELS = 'LLL:EXT:c2pa_reader/Resources/Private/Language/locallang_db.xlf:';

    public function __construct(
        private readonly CredentialReader $reader,
        private readonly MetaDataRepository $metaDataRepository,
        private readonly FlashMessageService $flashMessageService,
        private readonly LanguageServiceFactory $languageServiceFactory,
        private readonly IssueCatalog $issueCatalog,
    ) {}

    #[AsEventListener(identifier: 'c2pa-reader/after-file-added')]
    public function onFileAdded(AfterFileAddedEvent $event): void
    {
        $this->process($event->getFile());
    }

    #[AsEventListener(identifier: 'c2pa-reader/after-file-replaced')]
    public function onFileReplaced(AfterFileReplacedEvent $event): void
    {
        $this->process($event->getFile());
    }

    private function process(FileInterface $file): void
    {
        // Only indexed files (File) have metadata, not ProcessedFile and the like.
        if (!$file instanceof File || !$this->reader->supports($file)) {
            return;
        }
        $result = $this->reader->read($file);
        $this->metaDataRepository->update($file->getUid(), $result);

        if ($result['tx_c2pareader_status'] === CredentialReader::STATUS_INVALID) {
            $this->notifyBackendUser($file->getName(), $result['tx_c2pareader_issues']);
        }
    }

    /**
     * A warning, not an error: the upload itself succeeded, and an error would make the
     * backend's upload dialog report it as failed. The drag & drop uploader shows queued
     * messages as notifications; other backend views show them on the next page.
     */
    private function notifyBackendUser(string $fileName, string $issues): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$request instanceof ServerRequestInterface
            || !ApplicationType::fromRequest($request)->isBackend()
            || !$backendUser instanceof BackendUserAuthentication
        ) {
            return;
        }

        $languageService = $this->languageServiceFactory->createFromUserPreferences($backendUser);
        $message = $fileName . ': ' . implode(' ', $this->issueCatalog->sentences($issues, $languageService));
        $this->flashMessageService->getMessageQueueByIdentifier()->enqueue(new FlashMessage(
            $message,
            $languageService->sL(self::LABELS . 'upload.invalid.title'),
            ContextualFeedbackSeverity::WARNING,
            true,
        ));
    }
}
