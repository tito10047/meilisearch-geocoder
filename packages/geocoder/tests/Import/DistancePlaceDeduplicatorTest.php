<?php

namespace Tito10047\Geocoder\Tests\Import;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\DistancePlaceDeduplicator;
use Tito10047\Geocoder\Tests\Fake\Documents;

final class DistancePlaceDeduplicatorTest extends TestCase
{
    public function testKeepsTheHighestRankAndTakesThePostcode(): void
    {
        // Žilina: the relation of type "town" has no postcode, the node of type "city" has one
        $relation = Documents::create(id: 'R1859537', type: 'town', latitude: 49.2194, longitude: 18.7408, rank: 70500);
        $node = Documents::create(id: 'N26037035', type: 'city', postcode: '010 01', latitude: 49.2234, longitude: 18.7394, rank: 90600);

        $kept = (new DistancePlaceDeduplicator(5.0))->deduplicate([$relation, $node]);

        $this->assertCount(1, $kept);
        $this->assertSame('N26037035', $kept[0]->id);
        $this->assertSame('010 01', $kept[0]->postcode);
    }

    public function testFillsAMissingPostcodeOfTheKeptPlace(): void
    {
        $relation = Documents::create(id: 'R1', rank: 90656);
        $node = Documents::create(id: 'N1', postcode: '041 26', latitude: 48.42, rank: 90600);
        $other = Documents::create(id: 'N2', postcode: '999 99', latitude: 48.41, rank: 100);

        $kept = (new DistancePlaceDeduplicator(5.0))->deduplicate([$relation, $node, $other]);

        $this->assertCount(1, $kept);
        $this->assertSame('R1', $kept[0]->id);
        $this->assertSame('041 26', $kept[0]->postcode);
        $this->assertSame(['hu' => 'Szőlőske'], $kept[0]->namesI18n);
        $this->assertSame(90656, $kept[0]->rank);
    }

    public function testKeepsPlacesFarApart(): void
    {
        $village = Documents::create(id: 'N26037951', latitude: 48.3997, longitude: 21.7476, rank: 50480);
        $hamlet = Documents::create(id: 'N417021366', type: 'hamlet', latitude: 48.3055, longitude: 19.2951, rank: 30133);
        // 0.05° of latitude is 5.56 km, more than the 5 km
        $near = Documents::create(id: 'N3', latitude: 48.4497, longitude: 21.7476, rank: 30000);

        $kept = (new DistancePlaceDeduplicator(5.0))->deduplicate([$hamlet, $near, $village]);

        $this->assertSame(['N26037951', 'N417021366', 'N3'], array_map(static fn (Document $document): string => $document->id, $kept));
    }

    public function testPlacesOfTheSameRankStayInTheirOrder(): void
    {
        $first = Documents::create(id: 'N1');
        $second = Documents::create(id: 'N2', postcode: '076 31');

        $kept = (new DistancePlaceDeduplicator(5.0))->deduplicate([$first, $second]);

        $this->assertSame('N1', $kept[0]->id);
        $this->assertSame('076 31', $kept[0]->postcode);
    }

    public function testNoDistanceKeepsEverything(): void
    {
        $this->assertCount(2, (new DistancePlaceDeduplicator(0.0))->deduplicate([Documents::create(id: 'N1'), Documents::create(id: 'N2')]));
        $this->assertSame([], (new DistancePlaceDeduplicator(5.0))->deduplicate([]));
    }

    public function testRejectsANegativeDistance(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DistancePlaceDeduplicator(-1.0);
    }
}
