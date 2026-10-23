<?php

namespace Tito10047\Geocoder\Tests\Import;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\DumpSummary;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\Builds;

final class BuildTest extends TestCase
{
    public function testSumsTheCountsOfTheDumps(): void
    {
        $this->assertEquals(new ImportCounts(11, 22, 6, 2), Builds::build(new TableNames())->counts());
    }

    public function testKeepsTheOldestDataTimestampOfEachCountry(): void
    {
        $build = Builds::build(new TableNames());

        $this->assertEquals([
            'AT' => new \DateTimeImmutable('2026-10-16T08:00:00+00:00'),
            'SK' => new \DateTimeImmutable('2026-10-16T08:00:00+00:00'),
        ], $build->dataTimestamps());
        $this->assertEquals(new \DateTimeImmutable('2026-10-16T08:00:00+00:00'), $build->oldestDataTimestamp());
    }

    public function testMovesToOtherTables(): void
    {
        $build = Builds::build((new TableNames())->withSuffix('_1'));

        $live = $build->withTables(new TableNames());

        $this->assertEquals(new TableNames(), $live->tables);
        $this->assertSame($build->dumps, $live->dumps);
        $this->assertSame($build->indexUid, $live->indexUid);
    }

    /**
     * @param array<mixed> $dumps
     */
    #[DataProvider('invalidBuilds')]
    public function testRejectsAnInvalidBuild(string $indexUid, string $settingsHash, int $schemaVersion, array $dumps): void
    {
        $this->expectException(InvalidArgumentException::class);

        /* @phpstan-ignore argument.type (the invalid dumps are the point) */
        new Build($indexUid, new TableNames(), new \DateTimeImmutable(), $settingsHash, $schemaVersion, $dumps);
    }

    /**
     * @return iterable<string, array{string, string, int, array<mixed>}>
     */
    public static function invalidBuilds(): iterable
    {
        $hash = str_repeat('a', 64);
        $dump = new DumpSummary('a', new \DateTimeImmutable(), ['SK'], new ImportCounts());

        yield 'empty index uid' => ['', $hash, 1, [$dump]];
        yield 'settings hash not hexadecimal' => ['geo_1', str_repeat('A', 64), 1, [$dump]];
        yield 'schema version 0' => ['geo_1', $hash, 0, [$dump]];
        yield 'no dump' => ['geo_1', $hash, 1, []];
        yield 'not a list' => ['geo_1', $hash, 1, ['a' => $dump]];
        yield 'not a dump summary' => ['geo_1', $hash, 1, ['slovakia.jsonl']];
    }
}
