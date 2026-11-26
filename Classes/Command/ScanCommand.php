<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Command;

use Dkd\C2paReader\Service\CredentialScanner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks existing files: vendor/bin/typo3 c2pa:scan [--force]
 */
#[AsCommand(name: 'c2pa:scan', description: 'Checks existing files for C2PA Content Credentials.')]
final class ScanCommand extends Command
{
    public function __construct(private readonly CredentialScanner $scanner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-check files that were already checked.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $counts = $this->scanner->scan(
            (bool)$input->getOption('force'),
            static fn(int $uid, \Throwable $e) => $io->warning(sprintf('File %d skipped: %s', $uid, $e->getMessage())),
        );
        self::printCounts($io, $counts);

        return Command::SUCCESS;
    }

    /** @param array<string, int> $counts */
    public static function printCounts(SymfonyStyle $io, array $counts): void
    {
        $io->table(['Status', 'Files'], array_map(null, array_keys($counts), array_values($counts)));
    }
}
