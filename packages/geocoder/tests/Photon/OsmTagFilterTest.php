<?php

namespace Tito10047\Geocoder\Tests\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Photon\Feature;
use Tito10047\Geocoder\Photon\Layer;
use Tito10047\Geocoder\Photon\OsmTagFilter;

final class OsmTagFilterTest extends TestCase
{
    #[DataProvider('provideForms')]
    public function testParsesTheSixForms(string $filter, ?string $key, ?string $value, bool $excludes): void
    {
        $this->assertEquals(new OsmTagFilter($key, $value, $excludes), OsmTagFilter::fromString($filter));
    }

    /**
     * @return iterable<string, array{string, string|null, string|null, bool}>
     */
    public static function provideForms(): iterable
    {
        yield 'key and value' => ['tourism:hotel', 'tourism', 'hotel', false];
        yield 'not key and value' => ['!tourism:hotel', 'tourism', 'hotel', true];
        yield 'key' => ['tourism', 'tourism', null, false];
        yield 'value' => [':hotel', null, 'hotel', false];
        yield 'not key' => ['!tourism', 'tourism', null, true];
        yield 'not value' => [':!hotel', null, 'hotel', true];
    }

    #[DataProvider('provideInvalidFilters')]
    public function testRejectsAnInvalidFilter(string $filter): void
    {
        $this->expectException(InvalidArgumentException::class);

        OsmTagFilter::fromString($filter);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidFilters(): iterable
    {
        yield 'empty' => [''];
        yield 'colon' => [':'];
        yield 'exclamation mark' => ['!'];
        yield 'three parts' => ['a:b:c'];
        yield 'negated value after a key' => ['a:!b'];
        yield 'empty key' => ['!:b'];
    }

    public function testNeedsAKeyOrAValue(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new OsmTagFilter(null, null);
    }

    public function testMatchesTheTag(): void
    {
        $hotel = new Feature(new Coordinates(0, 0), Layer::House, 'SK', osmKey: 'tourism', osmValue: 'hotel');

        $this->assertTrue((new OsmTagFilter('tourism', 'hotel'))->matches($hotel));
        $this->assertTrue((new OsmTagFilter(null, 'hotel', true))->matches($hotel), 'the exclusion is applied by the query');
        $this->assertFalse((new OsmTagFilter('tourism', 'museum'))->matches($hotel));
        $this->assertFalse((new OsmTagFilter('amenity', null))->matches($hotel));
    }
}
