<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Command;

use Dkd\C2paReader\Service\CredentialReader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Index\MetaDataRepository;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * Bestandsdateien nachträglich prüfen: vendor/bin/typo3 c2pa:scan [--force]
 */
#[AsCommand(name: 'c2pa:scan', description: 'Scans existing JPEG/PNG files for C2PA Content Credentials.')]
final class ScanCommand extends Command
{
    public function __construct(
        private readonly CredentialReader $reader,
        private readonly MetaDataRepository $metaDataRepository,
        private readonly ResourceFactory $resourceFactory,
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-check files that were already checked.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $qb = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $qb->select('f.uid')
            ->from('sys_file', 'f')
            ->where($qb->expr()->in(
                'f.mime_type',
                $qb->createNamedParameter(CredentialReader::SUPPORTED_MIME_TYPES,\TYPO3\CMS\Core\Database\Connection::PARAM_STR_ARRAY),
            ));

        if (!$input->getOption('force')) {
            $qb->leftJoin('f', 'sys_file_metadata', 'm', $qb->expr()->eq('m.file', 'f.uid'))
                ->andWhere($qb->expr()->or(
                    $qb->expr()->isNull('m.uid'),
                    $qb->expr()->eq('m.tx_c2pareader_checked', 0),
                ));
        }

        $counts = [];
        foreach ($qb->executeQuery()->fetchFirstColumn() as $uid) {
            try {
                $file = $this->resourceFactory->getFileObject((int)$uid);
                if (!$this->reader->supports($file)) {
                    continue;
                }
                $data = $this->reader->read($file);
                $this->metaDataRepository->update((int)$uid, $data);
                $status = $data['tx_c2pareader_status'];
                $counts[$status] = ($counts[$status] ?? 0) + 1;
            } catch (\Throwable $e) {
                $io->warning(sprintf('File %d skipped: %s', $uid, $e->getMessage()));
            }
        }

        $io->table(['Status', 'Files'], array_map(null, array_keys($counts), array_values($counts)));
        return Command::SUCCESS;
    }
}
