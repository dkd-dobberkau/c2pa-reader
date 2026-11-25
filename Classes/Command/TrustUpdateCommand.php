<?php

declare(strict_types=1);

namespace Dkd\C2paReader\Command;

use Dkd\C2paReader\Trust\TrustListUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Downloads the C2PA trust lists: vendor/bin/typo3 c2pa:trust:update [--with-digicert]
 */
#[AsCommand(name: 'c2pa:trust:update', description: 'Downloads the trust lists of the C2PA conformance programme.')]
final class TrustUpdateCommand extends Command
{
    public function __construct(private readonly TrustListUpdater $updater)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'with-digicert',
            null,
            InputOption::VALUE_NONE,
            'Also trust timestamps from DigiCert Trusted Root G4 (used by Adobe and Microsoft; not on the C2PA TSA list).',
        );
        $this->setHelp(
            'Downloads the signer list and the timestamp authority (TSA) list that the C2PA conformance programme publishes '
            . '(github.com/c2pa-org/conformance-public, CC BY 4.0) into the trust directory. Files whose signer chains to '
            . 'one of these anchors get the status "trusted". Run it again to refresh the lists, then c2pa:scan --force.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $result = $this->updater->update((bool)$input->getOption('with-digicert'));
        } catch (\Throwable $e) {
            $io->error('The trust lists were not updated: ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->table(['File', 'SHA-256', 'Source'], array_map(
            static fn(array $file): array => [$file['file'], substr($file['sha256'], 0, 16) . '…', $file['url']],
            $result['files'],
        ));
        $io->success('Trust lists saved in ' . $result['directory'] . '.');
        $io->note('Run "vendor/bin/typo3 c2pa:scan --force" to check existing files against the new lists.');

        return Command::SUCCESS;
    }
}
