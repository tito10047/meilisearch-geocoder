<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Valhalla\AdminCrossing;
use Tito10047\Routing\Valhalla\Leg;
use Tito10047\Routing\Valhalla\ShapeSpan;

final class LegTest extends TestCase
{
    public function testScalesThePositionsToTheLengthReportedByValhalla(): void
    {
        // the 4 segments measure about 1112 m each, Valhalla says the leg is 4000 m
        $leg = Legs::straight(5, 4000.0);

        foreach ([0.0, 1000.0, 2000.0, 3000.0, 4000.0] as $index => $position) {
            $this->assertEqualsWithDelta($position, $leg->positionOf($index), 1e-6);
        }
    }

    public function testFollowsTheShapeOfTheLine(): void
    {
        // 1 km east, then 3 km north (on the equator, a degree is the same distance both ways)
        $shape = [new Coordinates(0.0, 0.0), new Coordinates(0.0, 0.01), new Coordinates(0.03, 0.01)];
        $leg = new Leg($shape, 4000.0, [], [], []);

        $this->assertEqualsWithDelta(1000.0, $leg->positionOf(1), 1e-3);
        $this->assertEqualsWithDelta(4000.0, $leg->positionOf(2), 1e-6);
    }

    public function testSpreadsTheLengthOverADegenerateShape(): void
    {
        $point = new Coordinates(48.1486, 17.1077);
        $leg = new Leg([$point, $point, $point], 10.0, [], [], []);

        $this->assertEqualsWithDelta(0.0, $leg->positionOf(0), \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(5.0, $leg->positionOf(1), \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(10.0, $leg->positionOf(2), \PHP_FLOAT_EPSILON);
    }

    public function testHoldsWhatItIsBuiltFrom(): void
    {
        $leg = Legs::straight(4, 300.0, ['SK', 'HU'], [[0, 1, 1, 2]], [[0, 3]]);

        $this->assertCount(4, $leg->shape);
        $this->assertEqualsWithDelta(300.0, $leg->lengthMeters, \PHP_FLOAT_EPSILON);
        $this->assertSame(['SK', 'HU'], $leg->countries);
        $this->assertCount(1, $leg->crossings);
        $this->assertCount(1, $leg->tolled);
    }

    public function testRejectsAPositionOutsideTheShape(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Legs::straight(3, 100.0)->positionOf(3);
    }

    /**
     * @param array<mixed>        $shape
     * @param list<string>        $countries
     * @param list<AdminCrossing> $crossings
     * @param list<ShapeSpan>     $tolled
     */
    #[DataProvider('provideInvalidLegs')]
    public function testRejectsInvalidValues(array $shape, float $lengthMeters, array $countries, array $crossings, array $tolled): void
    {
        $this->expectException(InvalidArgumentException::class);

        /* @phpstan-ignore argument.type (the invalid shapes are the point of the test) */
        new Leg($shape, $lengthMeters, $countries, $crossings, $tolled);
    }

    /**
     * @return iterable<string, array{array<mixed>, float, list<string>, list<AdminCrossing>, list<ShapeSpan>}>
     */
    public static function provideInvalidLegs(): iterable
    {
        $shape = [new Coordinates(0.0, 0.0), new Coordinates(0.0, 0.01), new Coordinates(0.0, 0.02)];

        yield 'one point' => [[$shape[0]], 10.0, [], [], []];
        yield 'shape not a list' => [[1 => $shape[0], 2 => $shape[1]], 10.0, [], [], []];
        yield 'shape of arrays' => [[[0.0, 0.0], [0.0, 0.01]], 10.0, [], [], []];
        yield 'negative length' => [$shape, -1.0, [], [], []];
        yield 'infinite length' => [$shape, \INF, [], [], []];
        yield 'lower case country' => [$shape, 10.0, ['sk'], [], []];
        yield 'crossing to an unknown admin' => [$shape, 10.0, ['SK'], [new AdminCrossing(0, 1, new ShapeSpan(0, 1))], []];
        yield 'crossing outside the shape' => [$shape, 10.0, ['SK', 'HU'], [new AdminCrossing(0, 1, new ShapeSpan(2, 3))], []];
        yield 'toll outside the shape' => [$shape, 10.0, [], [], [new ShapeSpan(0, 3)]];
    }
}
