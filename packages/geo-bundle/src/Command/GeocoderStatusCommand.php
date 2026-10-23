<?php

namespace Tito10047\GeoBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\GeoBundle\Attribution;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexSettings;

/**
 * Prints the state of the live index: its size, whether it is being indexed, its settings.
 */
#[AsCommand(name: 'tito10047-geo:geocoder:status', description: 'Prints the state of the live index of the geocoder')]
final class GeocoderStatusCommand extends Command
{
    /**
     * @param string $indexUid the uid of the live index
     */
    public function __construct(
        private readonly IndexManagerInterface $indexes,
        private readonly string $indexUid,
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
            ['Documents' => number_format($status->documents, 0, '.', ' ')],
            ['Stored documents' => Helper::formatMemory($status->rawDocumentDbSize)],
            ['Meilisearch database' => Helper::formatMemory($status->databaseSize).' (every index)'],
            ['Indexing' => $status->indexing ? 'yes' : 'no'],
            ['Settings' => $settings],
            ['Meilisearch' => $status->meilisearchVersion],
        );
        $io->text(Attribution::OPENSTREETMAP);

        return Command::SUCCESS;
    }
}
