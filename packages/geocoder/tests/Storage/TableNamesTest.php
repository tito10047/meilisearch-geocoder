<?php

namespace Tito10047\Geocoder\Tests\Storage;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Storage\TableNames;

final class TableNamesTest extends TestCase
{
    public function testHasDefaults(): void
    {
        $tables = new TableNames();

        $this->assertSame('geo_house', $tables->houseNumbers);
        $this->assertSame('geo_name', $tables->translations);
        $this->assertSame('geo_meta', $tables->metadata);
    }

    public function testWithSuffix(): void
    {
        $tables = (new TableNames())->withSuffix('_20261023');

        $this->assertSame('geo_house_20261023', $tables->houseNumbers);
        $this->assertSame('geo_name_20261023', $tables->translations);
        $this->assertSame('geo_meta_20261023', $tables->metadata);
    }

    #[DataProvider('provideInvalidNames')]
    public function testRejectsInvalidNames(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TableNames(houseNumbers: $name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'leading digit' => ['1geo'];
        yield 'injection' => ['geo; DROP TABLE x'];
        yield 'quoted' => ['"geo"'];
        yield 'too long' => [str_repeat('a', 49)];
    }
}
