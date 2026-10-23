<?php

namespace Tito10047\Routing\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Costing;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\RouteRequest;

final class RouteRequestTest extends TestCase
{
    public function testHasDefaults(): void
    {
        $points = [new Coordinates(48.1486, 17.1077), new Coordinates(48.7164, 21.2611)];
        $request = new RouteRequest($points);

        $this->assertSame($points, $request->points);
        $this->assertSame(1, $request->alternatives);
        $this->assertSame(Costing::Car, $request->costing);
        $this->assertSame(200, $request->radiusMeters);
        $this->assertFalse($request->avoidTolls);
        $this->assertFalse($request->avoidHighways);
    }

    public function testAcceptsStopsAndOptions(): void
    {
        $points = [new Coordinates(48.1486, 17.1077), new Coordinates(48.2082, 16.3738), new Coordinates(49.1951, 16.6068)];
        $request = new RouteRequest($points, 3, Costing::Truck, 0, true, true);

        $this->assertCount(3, $request->points);
        $this->assertSame(3, $request->alternatives);
        $this->assertSame(Costing::Truck, $request->costing);
        $this->assertSame(0, $request->radiusMeters);
        $this->assertTrue($request->avoidTolls);
        $this->assertTrue($request->avoidHighways);
    }

    /**
     * @param array<mixed> $points
     */
    #[DataProvider('provideInvalidRequests')]
    public function testRejectsInvalidValues(array $points, int $alternatives, int $radiusMeters): void
    {
        $this->expectException(InvalidArgumentException::class);

        /* @phpstan-ignore argument.type (the invalid points are the point of the test) */
        new RouteRequest($points, $alternatives, radiusMeters: $radiusMeters);
    }

    /**
     * @return iterable<string, array{array<mixed>, int, int}>
     */
    public static function provideInvalidRequests(): iterable
    {
        $a = new Coordinates(48.1486, 17.1077);
        $b = new Coordinates(48.7164, 21.2611);

        yield 'no point' => [[], 1, 200];
        yield 'one point' => [[$a], 1, 200];
        yield 'not a list' => [[1 => $a, 2 => $b], 1, 200];
        yield 'not coordinates' => [[$a, [48.7, 21.2]], 1, 200];
        yield 'no alternative' => [[$a, $b], 0, 200];
        yield 'negative radius' => [[$a, $b], 1, -1];
    }
}
