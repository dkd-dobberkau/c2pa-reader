<?php

declare(strict_types=1);

namespace Dkd\C2paReader\EventListener;

use Dkd\C2paReader\Service\CredentialReader;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Resource\Event\AfterFileAddedEvent;
use TYPO3\CMS\Core\Resource\Event\AfterFileReplacedEvent;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;

/**
 * Prüft Content Credentials nach Upload oder Ersetzen einer Datei
 * und schreibt das Ergebnis in sys_file_metadata.
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

    private function process(\TYPO3\CMS\Core\Resource\FileInterface $file): void
    {
        if (!$this->reader->supports($file)) {
            return;
        }
        $this->metaDataRepository->update($file->getUid(), $this->reader->read($file));
    }
}
