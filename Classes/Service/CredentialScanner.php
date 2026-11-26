<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Checks the existing files of a supported type and stores the results.
 * Used by c2pa:scan and by c2pa:trust:update --rescan.
 */
final class CredentialScanner
{
    public function __construct(
        private readonly CredentialReader $reader,
        private readonly MetaDataRepository $metaDataRepository,
        private readonly ResourceFactory $resourceFactory,
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param bool $force check files that were checked before, too
     * @param (callable(int, \Throwable): void)|null $onError called for a file that was skipped
     * @return array<string, int> number of files per status
     */
    public function scan(bool $force, ?callable $onError = null): array
    {
        $counts = [];
        foreach ($this->fileUids($force) as $uid) {
            try {
                $file = $this->resourceFactory->getFileObject($uid);
                if (!$this->reader->supports($file)) {
                    continue;
                }
                $data = $this->reader->read($file);
                $this->metaDataRepository->update($uid, $data);
                $status = $data['tx_c2pareader_status'];
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            } catch (\Throwable $e) {
                if ($onError !== null) {
                    $onError($uid, $e);
                }
            }
        }

        return $counts;
    }

    /** @return list<int> */
    private function fileUids(bool $force): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $queryBuilder->select('f.uid')
            ->from('sys_file', 'f')
            ->where($queryBuilder->expr()->in(
                'f.mime_type',
                $queryBuilder->createNamedParameter(CredentialReader::SUPPORTED_MIME_TYPES, Connection::PARAM_STR_ARRAY),
            ));
        if (!$force) {
            $queryBuilder->leftJoin('f', 'sys_file_metadata', 'm', $queryBuilder->expr()->eq('m.file', 'f.uid'))
                ->andWhere($queryBuilder->expr()->or(
                    $queryBuilder->expr()->isNull('m.uid'),
                    $queryBuilder->expr()->eq('m.tx_c2pareader_checked', 0),
                ));
        }

        return array_map('intval', $queryBuilder->executeQuery()->fetchFirstColumn());
    }
}
