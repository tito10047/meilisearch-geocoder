<?php

namespace Tito10047\GeoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Command\Output\BuildPrinter;
use Tito10047\GeoBundle\Command\Output\ConsoleImportProgress;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\File\FileExporterInterface;
use Tito10047\Geocoder\Import\ImporterInterface;

/**
 * Imports Photon dumps (or exports of this command) into a new build and publishes it; or
 * converts the dumps into files only.
 */
#[AsCommand(name: 'tito10047-geo:geocoder:import', description: 'Imports Photon dumps into a new index and new tables, and publishes them', help: <<<'TXT'
The <info>%command.name%</info> command builds a new index and new tables from Photon dumps
(*.jsonl or *.jsonl.zst) while searches keep using the live ones, then publishes the build:

  <info>php %command.full_name%</info>                      the dumps of tito10047_geo.geocoder.import.dumps_dir
  <info>php %command.full_name% var/geo/slovakia.jsonl.zst</info>
  <info>php %command.full_name% --no-publish</info>         build only; publish later with tito10047-geo:geocoder:publish

With <comment>--export=<dir></comment> the dumps are converted into files only (no Meilisearch needed), to be
indexed on another machine: give that directory to this command there, like a dump.
TXT)]
final class GeocoderImportCommand extends Command
{
    /**
     * @param ImporterInterface|null $importer       null when the bundle has no URL of Meilisearch: exports only
     * @param string|null            $dumpsDirectory where the dumps are when none is given
     */
    public function __construct(
        private readonly ?ImporterInterface $importer,
        private readonly FileExporterInterface $exporter,
        private readonly ?string $dumpsDirectory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('dumps', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Photon dumps, directories exported by --export or "php://stdin"; the *.jsonl.zst and *.jsonl files of the configured dumps_dir by default')
            ->addOption('no-publish', null, InputOption::VALUE_NONE, 'Build only, leave the live index and tables as they are')
            ->addOption('export', null, InputOption::VALUE_REQUIRED, 'Convert into this directory (new or empty) instead of importing: the documents and an SQLite database, without Meilisearch')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dumps = $this->dumps($input, $io);
        if (null === $dumps) {
            return Command::FAILURE;
        }
        /** @var string|null $export */
        $export = $input->getOption('export');
        if (null === $export && !$this->importer instanceof ImporterInterface) {
            $io->error('Importing needs Meilisearch: configure "tito10047_geo.geocoder.meilisearch.url", or convert into files only with --export=<dir>.');

            return Command::FAILURE;
        }

        $progress = new ConsoleImportProgress($output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);
        try {
            if (null !== $export) {
                $build = $this->exporter->export($dumps, $export, $progress);
                $progress->finish();
                $this->printBuild($io, $build);
                $io->success(\sprintf('Exported into "%s". Index it with: %s "%s"', $export, $this->getName(), $export));

                return Command::SUCCESS;
            }
            $build = $this->importer->import($dumps, $progress);
            $progress->finish();
            $this->printBuild($io, $build);
        } catch (ExceptionInterface $e) {
            $progress->finish();
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if ($input->getOption('no-publish')) {
            $io->success(\sprintf('The build %s is ready; nothing live changed. Publish it with: tito10047-geo:geocoder:publish %1$s', $build->indexUid));

            return Command::SUCCESS;
        }

        try {
            $this->importer->publish($build);
        } catch (ExceptionInterface $e) {
            $io->error(\sprintf('The build %s was imported but not published: %s', $build->indexUid, $e->getMessage()));

            return Command::FAILURE;
        }
        $io->success(\sprintf('The build %s is live.', $build->indexUid));

        return Command::SUCCESS;
    }

    /**
     * @return list<string>|null null when there is none (the error is printed)
     */
    private function dumps(InputInterface $input, SymfonyStyle $io): ?array
    {
        /** @var list<string> $dumps */
        $dumps = $input->getArgument('dumps');
        if ([] !== $dumps) {
            return $dumps;
        }

        if (null === $this->dumpsDirectory) {
            $io->error('Give the dumps to import, or configure their directory "tito10047_geo.geocoder.import.dumps_dir".');

            return null;
        }
        if (!is_dir($this->dumpsDirectory)) {
            $io->error(\sprintf('The directory of the dumps "%s" does not exist.', $this->dumpsDirectory));

            return null;
        }

        $dumps = [...(glob($this->dumpsDirectory.'/*.jsonl.zst') ?: []), ...(glob($this->dumpsDirectory.'/*.jsonl') ?: [])];
        sort($dumps);
        if ([] === $dumps) {
            $io->error(\sprintf('There is no dump (*.jsonl.zst or *.jsonl) in "%s".', $this->dumpsDirectory));

            return null;
        }

        return $dumps;
    }

    private function printBuild(SymfonyStyle $io, Build $build): void
    {
        (new BuildPrinter())->print($io, $build);
        $io->text(Attribution::OPENSTREETMAP);
    }
}
