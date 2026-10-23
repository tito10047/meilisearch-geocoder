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
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\IndexStatus;
use Tito10047\Geocoder\Status\GeocoderState;
use Tito10047\Geocoder\Status\GeocoderStatus;
use Tito10047\Geocoder\Status\GeocoderStatusProviderInterface;

/**
 * Prints the state of the live index (its size, whether it is being indexed, its settings) and
 * of the live build (its data timestamps and counts per dump, the schema version of its tables).
 *
 * Fails unless the live index answers: Meilisearch unreachable, the index missing or the geocoder
 * not configured. The live build is printed in every case, read from the database on its own.
 */
#[AsCommand(name: 'geo:status', description: 'Prints the state of the live index of the geocoder')]
final class GeocoderStatusCommand extends Command
{
    public function __construct(private readonly GeocoderStatusProviderInterface $statusProvider)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $status = $this->statusProvider->status();

        match ($status->state) {
            GeocoderState::Ready => $this->printIndex($io, $status->index ?? throw new \LogicException('A ready geocoder has an index.')),
            GeocoderState::NoIndex => $io->error(\sprintf('The live index %s does not exist in Meilisearch %s: import and publish a build first.', $status->indexUid, $status->meilisearchVersion)),
            GeocoderState::Unreachable => $io->error(\sprintf('Meilisearch cannot be asked for the live index %s: %s', $status->indexUid, $status->error)),
            GeocoderState::NotConfigured => $io->error((string) $status->error),
        };
        $this->printLiveBuild($io, $status);
        $io->text(Attribution::OPENSTREETMAP);

        return $status->state->isReady() ? Command::SUCCESS : Command::FAILURE;
    }

    private function printIndex(SymfonyStyle $io, IndexStatus $index): void
    {
        $settings = match ($index->settingsHash) {
            null => 'not the settings of this library',
            (new IndexSettings())->hash() => 'the default settings',
            default => 'custom settings ('.substr($index->settingsHash, 0, 12).')',
        };

        $io->definitionList(
            ['Index' => $index->uid],
            ['Documents' => Numbers::format($index->documents)],
            ['Stored documents' => Helper::formatMemory($index->rawDocumentDbSize)],
            ['Meilisearch database' => Helper::formatMemory($index->databaseSize).' (every index)'],
            ['Indexing' => $index->indexing ? 'yes' : 'no'],
            ['Settings' => $settings],
            ['Meilisearch' => $index->meilisearchVersion],
        );
    }

    private function printLiveBuild(SymfonyStyle $io, GeocoderStatus $status): void
    {
        if (null !== $status->buildError) {
            $io->warning('The live build cannot be read: '.$status->buildError);

            return;
        }
        if (!$status->liveBuild instanceof Build) {
            $io->note('No build was published yet: the index was not made by the import of this bundle, or its tables are missing.');

            return;
        }

        $io->section('Live build');
        (new BuildPrinter())->print($io, $status->liveBuild);
    }
}
