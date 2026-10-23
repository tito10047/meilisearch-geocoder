<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\DumpSummary;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\Pdo\PdoSchema;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\StreetKey;

final class Builds
{
    public static function build(TableNames $tables, string $indexUid = 'geo_20261023120000'): Build
    {
        return new Build(
            $indexUid,
            $tables,
            new \DateTimeImmutable('2026-10-23T12:00:00+00:00'),
            (new IndexSettings())->hash(),
            PdoSchema::VERSION,
            [
                new DumpSummary('slovakia.jsonl.zst', new \DateTimeImmutable('2026-10-17T23:02:06+00:00'), ['SK'], new ImportCounts(10, 20, 3, 2)),
                new DumpSummary('austria.jsonl.zst', new \DateTimeImmutable('2026-10-16T08:00:00+00:00'), ['AT', 'SK'], new ImportCounts(1, 2, 3, 0)),
            ],
        );
    }

    public static function houseNumber(): HouseNumber
    {
        return new HouseNumber(new StreetKey(42), '196/14', '900 01', new Coordinates(48.123456789012, 17.1), 'Staré Mesto', '7101722639');
    }

    /**
     * Creates the tables of the build in the database, with two house numbers and a translation.
     */
    public static function store(\PDO $pdo, TableNames $tables, string $indexUid = 'geo_20261023120000'): Build
    {
        $storage = new PdoBuildStorage($pdo);
        $storage->create($tables);
        $houseNumbers = $storage->houseNumberWriter($tables);
        $houseNumbers->write(self::houseNumber());
        $houseNumbers->write(new HouseNumber(new StreetKey(0xFFFFFFFFFFFFFFF), '7', null, new Coordinates(-1.5, 2.25)));
        $houseNumbers->close();
        $translations = $storage->translationWriter($tables);
        $translations->write(new Translation('SK', NameKind::City, 'Košice', 'hu', 'Kassa'));
        $translations->close();
        $build = self::build($tables, $indexUid);
        $storage->complete($build);

        return $build;
    }
}
