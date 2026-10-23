<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\DumpSummary;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Builds for the tests of the commands.
 */
final class Builds
{
    public const UID = 'geo_20261023120000';

    private function __construct()
    {
    }

    public static function build(string $uid = self::UID): Build
    {
        return new Build(
            $uid,
            (new TableNames())->withSuffix(substr($uid, 3)),
            new \DateTimeImmutable('2026-10-23 12:00:00 UTC'),
            (new IndexSettings())->hash(),
            1,
            [
                new DumpSummary('slovakia.jsonl.zst', new \DateTimeImmutable('2026-10-17 23:02:06 UTC'), ['SK'], new ImportCounts(2900, 61000, 4100, 1_234_567)),
                new DumpSummary('czechia.jsonl.zst', new \DateTimeImmutable('2026-10-16 20:00:00 UTC'), ['CZ'], new ImportCounts(15000, 120000, 9000, 3_000_000)),
            ],
        );
    }
}
