<?php

namespace Tito10047\Routing\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\CountrySegment;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Route;

final class RouteTest extends TestCase
{
    public function testHoldsItsValues(): void
    {
        $geometry = [new Coordinates(48.1486, 17.1077), new Coordinates(43.5081, 16.4402)];
        $route = new Route(800560, 26436, $geometry, [new CountrySegment('SK', 800560)], []);

        $this->assertSame(800560, $route->distanceMeters);
        $this->assertSame(26436, $route->durationSeconds);
        $this->assertSame($geometry, $route->geometry);
        $this->assertCount(1, $route->countries);
        $this->assertSame([], $route->tolled);
    }

    public function testSumsTheDistanceOfACountryEnteredTwice(): void
    {
        $route = new Route(800560, 26436, [], [
            new CountrySegment('SK', 20110),
            new CountrySegment('HU', 224827),
            new CountrySegment('SI', 16811),
            new CountrySegment('HU', 22376),
            new CountrySegment('HR', 516436),
        ], []);

        $this->assertSame(['SK' => 20110, 'HU' => 247203, 'SI' => 16811, 'HR' => 516436], $route->distanceByCountry());
    }

    public function testListsTheTolledDistancePerCountry(): void
    {
        $route = new Route(800560, 26436, [], [], [
            new CountrySegment('SK', 15051),
            new CountrySegment('HU', 162683),
        ]);

        $this->assertSame(['SK' => 15051, 'HU' => 162683], $route->tolledByCountry());
    }

    public function testHasNoCountriesWhenTheEngineSaysNothing(): void
    {
        $route = new Route(0, 0, [], [], []);

        $this->assertSame([], $route->distanceByCountry());
        $this->assertSame([], $route->tolledByCountry());
    }

    /**
     * @param array<mixed> $geometry
     * @param array<mixed> $countries
     * @param array<mixed> $tolled
     */
    #[DataProvider('provideInvalidRoutes')]
    public function testRejectsInvalidValues(int $distanceMeters, int $durationSeconds, array $geometry, array $countries, array $tolled): void
    {
        $this->expectException(InvalidArgumentException::class);

        /* @phpstan-ignore argument.type, argument.type, argument.type (the invalid lists are the point of the test) */
        new Route($distanceMeters, $durationSeconds, $geometry, $countries, $tolled);
    }

    /**
     * @return iterable<string, array{int, int, array<mixed>, array<mixed>, array<mixed>}>
     */
    public static function provideInvalidRoutes(): iterable
    {
        $point = new Coordinates(48.1486, 17.1077);
        $segment = new CountrySegment('SK', 1);

        yield 'negative distance' => [-1, 0, [], [], []];
        yield 'negative duration' => [0, -1, [], [], []];
        yield 'geometry not a list' => [0, 0, [1 => $point], [], []];
        yield 'geometry of arrays' => [0, 0, [[48.1, 17.1]], [], []];
        yield 'countries of points' => [0, 0, [], [$point], []];
        yield 'tolled not a list' => [0, 0, [], [], ['SK' => $segment]];
        yield 'tolled country twice' => [0, 0, [], [], [$segment, $segment]];
    }
}
