<?php

namespace Tito10047\GeoBundle\Command\Output;

use Symfony\Component\Console\Style\SymfonyStyle;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\DumpSummary;
use Tito10047\Geocoder\Index\IndexSettings;

/**
 * Prints a build: its uid, what it holds and how old the data of each dump and country is.
 *
 * @internal
 */
final class BuildPrinter
{
    private const DATE = 'Y-m-d H:i:s T';

    public function print(SymfonyStyle $io, Build $build): void
    {
        $counts = $build->counts();
        $countries = [];
        foreach ($build->dataTimestamps() as $countryCode => $timestamp) {
            $countries[] = $countryCode.' '.$timestamp->format('Y-m-d');
        }
        $io->definitionList(
            ['Build' => $build->indexUid],
            ['Started' => $build->startedAt->format(self::DATE)],
            ['Documents' => \sprintf('%s (%s places, %s streets, %s points of interest)', Numbers::format($counts->documents()), Numbers::format($counts->places), Numbers::format($counts->streets), Numbers::format($counts->pois))],
            ['House numbers' => Numbers::format($counts->houseNumbers)],
            ['Data of the countries' => [] === $countries ? 'none' : implode(', ', $countries)],
            ['Index settings' => (new IndexSettings())->hash() === $build->settingsHash ? 'the default settings' : 'custom settings ('.substr($build->settingsHash, 0, 12).')'],
            ['Schema version' => (string) $build->schemaVersion],
        );

        $io->table(
            ['Dump', 'Countries', 'Data from', 'Places', 'Streets', 'Points of interest', 'House numbers'],
            array_map(static fn (DumpSummary $dump): array => [
                $dump->dump,
                implode(', ', $dump->countryCodes),
                $dump->dataTimestamp->format(self::DATE),
                Numbers::format($dump->counts->places),
                Numbers::format($dump->counts->streets),
                Numbers::format($dump->counts->pois),
                Numbers::format($dump->counts->houseNumbers),
            ], $build->dumps),
        );
    }
}
