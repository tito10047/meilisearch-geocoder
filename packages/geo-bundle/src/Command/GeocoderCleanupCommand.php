<?php

namespace Tito10047\GeoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Import\ImporterInterface;

/**
 * Removes the builds left by failed or abandoned imports, or imported and never published.
 */
#[AsCommand(name: 'geo:cleanup', description: 'Removes the builds of the geocoder that are not live (indexes and tables)', help: <<<'TXT'
The <info>%command.name%</info> command removes the indexes and the tables of every build that is not live:
those of failed or abandoned imports and those imported with --no-publish and never published.
The live index and tables are never touched.

Do not run it while an import runs: its build would be removed. Keep builds with <comment>--keep</comment>:

  <info>php %command.full_name% --keep=geo_20261023120000</info>
TXT)]
final class GeocoderCleanupCommand extends Command
{
    public function __construct(private readonly ImporterInterface $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('keep', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'The uid of a build to keep, repeatable');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var list<string> $keep */
        $keep = $input->getOption('keep');

        try {
            $removed = $this->importer->cleanup($keep);
        } catch (ExceptionInterface $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ([] === $removed) {
            $io->success('There is no build to remove.');
        } else {
            $io->listing($removed);
            $io->success(\sprintf('Removed %d build(s).', \count($removed)));
        }

        return Command::SUCCESS;
    }
}
