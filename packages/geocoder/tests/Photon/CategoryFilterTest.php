<?php

namespace Tito10047\Geocoder\Tests\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Photon\CategoryFilter;
use Tito10047\Geocoder\Photon\Feature;
use Tito10047\Geocoder\Photon\Layer;

final class CategoryFilterTest extends TestCase
{
    public function testParsesTheList(): void
    {
        $this->assertSame([['osm', 'tourism', 'hotel'], ['food', 'shop']], CategoryFilter::fromString('osm.tourism.hotel,food.shop')->categories);
    }

    #[DataProvider('provideInvalidFilters')]
    public function testRejectsAnInvalidFilter(string $filter): void
    {
        $this->expectException(InvalidArgumentException::class);

        CategoryFilter::fromString($filter);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidFilters(): iterable
    {
        yield 'empty' => [''];
        yield 'group only' => ['osm'];
        yield 'empty label' => ['osm..hotel'];
        yield 'too deep' => ['a.b.c.d.e.f'];
        yield 'invalid character' => ['osm.tourism:hotel'];
        yield 'empty item' => ['osm.tourism,'];
    }

    public function testConstructorValidates(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CategoryFilter([]);
    }

    public function testMatchesByPrefix(): void
    {
        $hotel = new Feature(new Coordinates(0, 0), Layer::House, 'SK', osmKey: 'tourism', osmValue: 'hotel');

        $this->assertTrue(CategoryFilter::fromString('osm.tourism')->matchesAny($hotel));
        $this->assertTrue(CategoryFilter::fromString('osm.railway,osm.tourism.hotel')->matchesAny($hotel));
        $this->assertFalse(CategoryFilter::fromString('osm.tourism.hotel.big')->matchesAny($hotel));
        $this->assertFalse(CategoryFilter::fromString('osm.tour')->matchesAny($hotel), 'labels are compared whole');
        $this->assertTrue(CategoryFilter::fromString('osm.tourism,osm.tourism.hotel')->matchesAll($hotel));
        $this->assertFalse(CategoryFilter::fromString('osm.tourism,osm.railway')->matchesAll($hotel));
    }
}
