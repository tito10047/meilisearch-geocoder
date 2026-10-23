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
    }

    public function testImportCountsAdd(): void
    {
        $counts = (new ImportCounts(1, 2, 3, 4))->add(new ImportCounts(10, 20, 30, 40));

        $this->assertEquals(new ImportCounts(11, 22, 33, 44), $counts);
        $this->assertSame(66, $counts->documents());
    }
}
