<?php

namespace Tito10047\GeoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Command\Output\BuildPrinter;
use Tito10047\GeoBundle\Command\Output\Numbers;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Import\ImporterInterface;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexSettings;

/**
 * Prints the state of the live index (its size, whether it is being indexed, its settings) and
 * of the live build (its data timestamps and counts per dump, the schema version of its tables).
 */
#[AsCommand(name: 'geo:status', description: 'Prints the state of the live index of the geocoder')]
final class GeocoderStatusCommand extends Command
{
    /**
     * @param string                 $indexUid the uid of the live index
     * @param ImporterInterface|null $importer reads the metadata of the live build; none printed when null
     */
    public function __construct(
        private readonly IndexManagerInterface $indexes,
        private readonly string $indexUid,
        private readonly ?ImporterInterface $importer = null,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $status = $this->indexes->status($this->indexUid);
        } catch (ExceptionInterface $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $settings = match ($status->settingsHash) {
            null => 'not the settings of this library',
            (new IndexSettings())->hash() => 'the default settings',
            default => 'custom settings ('.substr($status->settingsHash, 0, 12).')',
        };

        $io->definitionList(
            ['Index' => $status->uid],
            ['Documents' => Numbers::format($status->documents)],
            ['Stored documents' => Helper::formatMemory($status->rawDocumentDbSize)],
            ['Meilisearch database' => Helper::formatMemory($status->databaseSize).' (every index)'],
            ['Indexing' => $status->indexing ? 'yes' : 'no'],
            ['Settings' => $settings],
            ['Meilisearch' => $status->meilisearchVersion],
        );
        $this->printLiveBuild($io);
        $io->text(Attribution::OPENSTREETMAP);

        return Command::SUCCESS;
    }

    private function printLiveBuild(SymfonyStyle $io): void
    {
        if (!$this->importer instanceof ImporterInterface) {
            return;
        }

        try {
            $build = $this->importer->live();
        } catch (BuildNotFoundException) {
            $io->note('No build was published yet: the index was not made by the import of this bundle, or its tables are missing.');

            return;
        } catch (ExceptionInterface $e) {
            // the index answered: its state is printed even when the database does not
            $io->warning('The live build cannot be read: '.$e->getMessage());

            return;
        }

        $io->section('Live build');
        (new BuildPrinter())->print($io, $build);
    }
}
