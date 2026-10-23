<?php

namespace Tito10047\GeoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\GeoBundle\Artifact\ArtifactInstallerFactoryInterface;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Command\Output\BuildPrinter;
use Tito10047\GeoBundle\Exception\ExceptionInterface as BundleException;
use Tito10047\Geocoder\Exception\ExceptionInterface;

/**
 * Installs an artifact on a server: the tables into the database of the application, the
 * snapshot into the data directory of a Meilisearch instance started afterwards.
 */
#[AsCommand(name: 'geo:artifact:install', description: 'Installs an artifact of the geocoder: its tables now, its snapshot at the next start of Meilisearch', help: <<<'TXT'
The <info>%command.name%</info> command verifies an artifact (checksums, versions), copies its snapshot into
a data directory of Meilisearch without a database and makes its tables live:

  <info>php %command.full_name% var/geo/artifact /srv/meili_data_new</info>

Then (re)start Meilisearch on that data directory with --import-snapshot (printed at the end).
The snapshot loads only into an instance of the version that made it: the version is read from
the running instance, or geo.geocoder.meilisearch.version, or <comment>--meilisearch-version</comment>.
TXT)]
final class GeocoderInstallArtifactCommand extends Command
{
    /**
     * @param \PDO $database the database of the live tables
     */
    public function __construct(
        private readonly ArtifactInstallerFactoryInterface $installers,
        private readonly \PDO $database,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('artifact', InputArgument::REQUIRED, 'The directory of the artifact')
            ->addArgument('data-directory', InputArgument::REQUIRED, 'The data directory of the Meilisearch instance, without a database (data.ms) yet')
            ->addOption('meilisearch-version', null, InputOption::VALUE_REQUIRED, 'The version of the instance that will load the snapshot, e.g. "1.35.1"')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $artifact */
        $artifact = $input->getArgument('artifact');
        /** @var string $dataDirectory */
        $dataDirectory = $input->getArgument('data-directory');
        $version = $input->getOption('meilisearch-version');
        if ('' === $version) {
            $io->error('The --meilisearch-version must not be empty.');

            return Command::FAILURE;
        }

        try {
            $installed = $this->installers->create(\is_string($version) ? $version : null)->install($artifact, $dataDirectory, $this->database);
        } catch (ExceptionInterface|BundleException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        (new BuildPrinter())->print($io, $installed->build);
        $io->text(Attribution::OPENSTREETMAP);
        $io->success(\sprintf('The tables of the build %s are live; the snapshot is in "%s".', $installed->manifest->indexUid, $installed->snapshotPath));
        $io->section('Next: start Meilisearch on the snapshot');
        $io->listing([
            \sprintf('Start Meilisearch %s on the data directory "%s" with the snapshot, as the instance sees its path:', $installed->manifest->meilisearchVersion, $dataDirectory)
                .\sprintf("\n  meilisearch --db-path %s/data.ms --import-snapshot %s", $dataDirectory, $installed->snapshotPath)
                .\sprintf("\n  (in Docker, with the directory mounted on /meili_data: --import-snapshot /meili_data/%s)", basename($installed->snapshotPath)),
            'Point the application at that instance if it is a new one, with the same master key.',
            \sprintf('Once it answers (geo:status), the snapshot "%s" may be deleted.', $installed->snapshotPath),
        ]);

        return Command::SUCCESS;
    }
}
