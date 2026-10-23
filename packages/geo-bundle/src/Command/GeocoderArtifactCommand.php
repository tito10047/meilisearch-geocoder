<?php

namespace Tito10047\GeoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Command\Output\Numbers;
use Tito10047\Geocoder\Artifact\ArtifactBuilderInterface;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Import\ImporterInterface;

/**
 * Packs the live build into an artifact: a Meilisearch snapshot, an SQLite database of the
 * tables and their manifest, to install on a server that never indexes.
 */
#[AsCommand(name: 'geo:artifact', description: 'Packs the live build of the geocoder into an artifact (Meilisearch snapshot + SQLite)', help: <<<'TXT'
The <info>%command.name%</info> command snapshots the Meilisearch instance of the live build and copies the
live tables into an SQLite database, with a manifest, into a new or empty directory:

  <info>php %command.full_name% var/geo/artifact</info>

The instance must hold the live index only (the snapshot holds every index), and its snapshot
directory must be reachable from here (geo.geocoder.meilisearch.snapshot_dir).
Install the artifact on the server with geo:artifact:install.
TXT)]
final class GeocoderArtifactCommand extends Command
{
    /**
     * @param ArtifactBuilderInterface|null $builder null when the snapshot directory is not configured
     */
    public function __construct(
        private readonly ImporterInterface $importer,
        private readonly ?ArtifactBuilderInterface $builder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('directory', InputArgument::REQUIRED, 'Where to write the artifact: a new or empty directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$this->builder instanceof ArtifactBuilderInterface) {
            $io->error('Building an artifact needs the snapshot directory of the Meilisearch instance: configure "geo.geocoder.meilisearch.snapshot_dir".');

            return Command::FAILURE;
        }
        /** @var string $directory */
        $directory = $input->getArgument('directory');

        try {
            $artifact = $this->builder->build($this->importer->live(), $directory);
        } catch (ExceptionInterface $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $manifest = $artifact->manifest;
        $io->definitionList(
            ['Artifact' => $artifact->directory],
            ['Build' => $manifest->indexUid],
            ['Index' => $manifest->liveUid],
            ['Documents' => Numbers::format($manifest->documents)],
            ['House numbers' => Numbers::format($manifest->counts->houseNumbers)],
            ['Meilisearch' => $manifest->meilisearchVersion.' (only an instance of this version loads the snapshot)'],
            ['Snapshot' => Helper::formatMemory($manifest->snapshot->size)],
            ['Database' => Helper::formatMemory($manifest->database->size)],
        );
        $io->text(Attribution::OPENSTREETMAP);
        $io->success(\sprintf('The artifact is in "%s". Copy the directory to the server and run there: geo:artifact:install <artifact> <Meilisearch data directory>', $artifact->directory));

        return Command::SUCCESS;
    }
}
