<?php

namespace Tito10047\Geocoder\Tests\Import;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\SelectionRules;

final class SelectionRulesTest extends TestCase
{
    public function testDefaultsAreThoseOfThePrototype(): void
    {
        $rules = new SelectionRules();

        $this->assertSame(90, $rules->placeRank('city'));
        $this->assertNull($rules->placeRank('locality'));
        $this->assertTrue($rules->isPoi('tourism', 'hotel'));
        $this->assertFalse($rules->isPoi('amenity', 'restaurant'));
        $this->assertFalse($rules->isPoi('unknown', 'hotel'));
        $this->assertTrue($rules->isStreet('residential'));
        $this->assertTrue($rules->isStreet('track'));
        $this->assertFalse($rules->isStreet('bus_stop'));
        $this->assertFalse($rules->isStreet('platform'));
    }

    public function testReferenceKeys(): void
    {
        $this->assertSame('ref:minvskaddress', (new SelectionRules())->referenceKey('SK'));
        $this->assertNull((new SelectionRules())->referenceKey('CZ'));
        $this->assertSame('ref:ruian:addr', (new SelectionRules(referenceKeys: ['CZ' => 'ref:ruian:addr']))->referenceKey('CZ'));
        $this->assertNull((new SelectionRules(referenceKeys: []))->referenceKey('SK'));
    }

    public function testNoHighwayExcluded(): void
    {
        $this->assertTrue((new SelectionRules(streetExcludedHighways: []))->isStreet('bus_stop'));
    }

    public function testRank(): void
    {
        $rules = new SelectionRules();

        $this->assertSame(90767, $rules->rank(90, 0.7678));
        $this->assertSame(25000, $rules->rank(25, 0.0));
        $this->assertSame(20999, $rules->rank(20, 1.5));
        $this->assertSame(20000, $rules->rank(20, -0.5));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('provideInvalidArguments')]
    public function testRejectsInvalidArguments(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SelectionRules(...$arguments);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidArguments(): iterable
    {
        yield 'place type without name' => [['placeTypes' => ['' => 90]]];
        yield 'place type with negative rank' => [['placeTypes' => ['city' => -1]]];
        yield 'place type with list' => [['placeTypes' => [90]]];
        yield 'poi values empty' => [['pois' => ['tourism' => []]]];
        yield 'poi values not a list' => [['pois' => ['tourism' => ['a' => 'hotel']]]];
        yield 'poi value empty' => [['pois' => ['tourism' => ['']]]];
        yield 'negative poi rank' => [['poiRank' => -1]];
        yield 'negative street rank' => [['streetRank' => -1]];
        yield 'invalid language' => [['languages' => ['SK']]];
        yield 'negative distance' => [['placeDedupKm' => -1.0]];
        yield 'excluded highway empty' => [['streetExcludedHighways' => ['']]];
        yield 'excluded highway not a string' => [['streetExcludedHighways' => [1]]];
        yield 'reference key of a lower-case country' => [['referenceKeys' => ['sk' => 'ref:minvskaddress']]];
        yield 'reference key of a list' => [['referenceKeys' => ['ref:minvskaddress']]];
        yield 'empty reference key' => [['referenceKeys' => ['SK' => '']]];
    }

    public function testImportCountsAdd(): void
    {
        $counts = (new ImportCounts(1, 2, 3, 4))->add(new ImportCounts(10, 20, 30, 40));

        $this->assertEquals(new ImportCounts(11, 22, 33, 44), $counts);
        $this->assertSame(66, $counts->documents());
    }
}
