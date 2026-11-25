<?php

declare(strict_types=1);

namespace Dkd\C2paReader\EventListener;

use Dkd\C2paReader\Service\CredentialReader;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;

/**
 * Checks Content Credentials after a file is uploaded or replaced
 * and writes the result to sys_file_metadata.
 */
final class ReadCredentialsOnUpload
{
    public function __construct(
        private readonly CredentialReader $reader,
        private readonly MetaDataRepository $metaDataRepository,
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
        $this->metaDataRepository->update($file->getUid(), $this->reader->read($file));
    }
}
