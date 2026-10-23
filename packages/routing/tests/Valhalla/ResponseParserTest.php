<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\CountrySegment;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Route;
use Tito10047\Routing\Tests\Fixtures\Fixture;
use Tito10047\Routing\Valhalla\ResponseParser;

final class ResponseParserTest extends TestCase
{
    public function testReadsTheCountriesAndTollsOfARouteThroughFourCountries(): void
    {
        $routes = (new ResponseParser())->parse(Fixture::responseBody('ba-split'));

        $this->assertCount(1, $routes);
        $route = $routes[0];
        $this->assertSame(800560, $route->distanceMeters);
        $this->assertSame(26436, $route->durationSeconds);
        $this->assertCount(10008, $route->geometry);
        // Hungary is entered twice: the motorway to Zagreb cuts through Slovenia near Lendava
        $this->assertSame([['SK', 20110], ['HU', 224827], ['SI', 16811], ['HU', 22376], ['HR', 516436]], $this->segments($route->countries));
        $this->assertSame(['SK' => 20110, 'HU' => 247203, 'SI' => 16811, 'HR' => 516436], $route->distanceByCountry());
        $this->assertSame(['SK' => 15051, 'HU' => 162683, 'SI' => 16307, 'HR' => 477321], $route->tolledByCountry());
    }

    public function testMatchesTheTollsMeasuredInTheResearch(): void
    {
        // _docs/06-valhalla.md: SK 15 / HU 163 / SI 16 / HR 477 km tolled, SK 20 / HU 247 / SI 17 / HR 516 km driven
        $route = (new ResponseParser())->parse(Fixture::responseBody('ba-split'))[0];

        $this->assertSame(['SK' => 15, 'HU' => 163, 'SI' => 16, 'HR' => 477], array_map(static fn (int $m): int => (int) round($m / 1000), $route->tolledByCountry()));
        $this->assertSame(['SK' => 20, 'HU' => 247, 'SI' => 17, 'HR' => 516], array_map(static fn (int $m): int => (int) round($m / 1000), $route->distanceByCountry()));
    }

    public function testTheTollsAddUpToTheTolledManeuvers(): void
    {
        $route = (new ResponseParser())->parse(Fixture::responseBody('ba-split'))[0];

        // the lengths of the maneuvers with "toll" in the fixture add up to 671.361 km
        $this->assertEqualsWithDelta(671361, array_sum($route->tolledByCountry()), 5);
    }

    public function testReadsTheAlternatives(): void
    {
        $routes = (new ResponseParser())->parse(Fixture::responseBody('ba-kosice-alternatives'));

        $this->assertSame([441442, 403705, 485685], array_map(static fn (Route $r): int => $r->distanceMeters, $routes));
        $this->assertSame([13503, 14532, 14452], array_map(static fn (Route $r): int => $r->durationSeconds, $routes));
        $this->assertSame([['SK', 441442]], $this->segments($routes[0]->countries));
        $this->assertSame(['SK' => 411011], $routes[0]->tolledByCountry());
        $this->assertSame(['SK' => 272052], $routes[1]->tolledByCountry());
        // the third goes through Hungary and back
        $this->assertSame([['SK', 20110], ['HU', 442571], ['SK', 23004]], $this->segments($routes[2]->countries));
        $this->assertSame(['SK' => 29126, 'HU' => 441093], $routes[2]->tolledByCountry());
    }

    public function testReadsARouteFromAPedestrianZoneSnappedWithARadius(): void
    {
        $route = (new ResponseParser())->parse(Fixture::responseBody('stephansplatz-with-radius'))[0];

        $this->assertSame(80028, $route->distanceMeters);
        $this->assertSame([['AT', 65892], ['SK', 14136]], $this->segments($route->countries));
        $this->assertSame(['AT' => 62017, 'SK' => 1660], $route->tolledByCountry());
    }

    public function testJoinsTheLegsOfARouteWithAStop(): void
    {
        $route = (new ResponseParser())->parse(Fixture::responseBody('multi-stop'))[0];

        $this->assertSame(215748, $route->distanceMeters);
        $this->assertSame(8657, $route->durationSeconds);
        // Austria is not listed twice although the stop in Vienna splits it into two legs
        $this->assertSame([['SK', 13019], ['AT', 150706], ['CZ', 52023]], $this->segments($route->countries));
        $this->assertSame(['SK' => 2466, 'AT' => 143895, 'CZ' => 44392], $route->tolledByCountry());
        // the second leg starts where the first ended, the point is not repeated
        $this->assertCount(3570, $route->geometry);
        $this->assertEqualsWithDelta(48.148594, $route->geometry[0]->latitude, \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(49.195002, $route->geometry[3569]->latitude, \PHP_FLOAT_EPSILON);
    }

    public function testTheCountriesAddUpToTheRoute(): void
    {
        foreach (['ba-kosice-alternatives', 'ba-split', 'stephansplatz-with-radius', 'multi-stop'] as $name) {
            foreach ((new ResponseParser())->parse(Fixture::responseBody($name)) as $index => $route) {
                $this->assertEqualsWithDelta($route->distanceMeters, array_sum($route->distanceByCountry()), 3, "$name, route $index");
            }
        }
    }

    public function testConvertsMiles(): void
    {
        $routes = (new ResponseParser())->parse(self::trip([
            'units' => 'miles',
            'summary' => ['length' => 1.0, 'time' => 60.4],
            'legs' => [self::leg(['summary' => ['length' => 1.0, 'admins' => [['country_code' => 'SK']]]])],
        ]));

        $this->assertSame(1609, $routes[0]->distanceMeters);
        $this->assertSame(60, $routes[0]->durationSeconds);
        $this->assertSame(['SK' => 1609], $routes[0]->distanceByCountry());
        // the second half of the leg is tolled
        $this->assertSame(['SK' => 805], $routes[0]->tolledByCountry());
    }

    public function testReadsARouteWithoutAdmins(): void
    {
        $body = self::trip(['legs' => [self::leg(['summary' => ['length' => 1.0]])]]);

        $route = (new ResponseParser())->parse($body)[0];

        $this->assertSame([], $route->countries);
        $this->assertSame([], $route->tolled);
        $this->assertCount(3, $route->geometry);
    }

    public function testRejectsInvalidJson(): void
    {
        try {
            (new ResponseParser())->parse('{"trip":');
            $this->fail('An exception was expected.');
        } catch (RoutingUnavailableException $e) {
            $this->assertStringStartsWith('Valhalla answered with invalid JSON', $e->getMessage());
            $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    #[DataProvider('provideUnexpectedAnswers')]
    public function testRejectsAnUnexpectedAnswer(string $body, string $message): void
    {
        try {
            (new ResponseParser())->parse($body);
            $this->fail('An exception was expected.');
        } catch (RoutingUnavailableException $e) {
            $this->assertStringStartsWith('Valhalla answered with an unexpected route: ', $e->getMessage());
            $this->assertStringContainsString($message, $e->getMessage());
            $this->assertInstanceOf(InvalidArgumentException::class, $e->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideUnexpectedAnswers(): iterable
    {
        yield 'a list' => ['[1]', 'An object was expected at the answer, array given'];
        yield 'a number' => ['42', 'An object was expected at the answer, int given'];
        yield 'no trip' => ['{"id":"x"}', 'An object was expected at trip, null given'];
        yield 'alternates not a list' => [self::trip(['alternates' => ['a' => 1]], true), 'A list was expected at alternates'];
        yield 'alternate without trip' => [self::trip(['alternates' => [['x' => 1]]], true), 'alternates[0].trip, null given'];
        yield 'unknown units' => [self::trip(['units' => 'furlongs']), 'The units "furlongs" at trip.units are not supported'];
        yield 'no summary' => [self::trip(['summary' => null]), 'An object was expected at trip.summary'];
        yield 'no length' => [self::trip(['summary' => ['time' => 1.0]]), 'A number was expected at trip.summary.length, null given'];
        yield 'no legs' => [self::trip(['legs' => []]), 'The trip at trip has no legs'];
        yield 'shape not a string' => [self::trip(['legs' => [self::leg(['shape' => 5])]]), 'A string was expected at trip.legs[0].shape, int given'];
        yield 'broken shape' => [self::trip(['legs' => [self::leg(['shape' => '_p~iF'])]]), 'The encoded polyline ends in the middle of a value'];
        yield 'leg length not a number' => [self::trip(['legs' => [self::leg(['summary' => ['length' => '1']])]]), 'A number was expected at trip.legs[0].summary.length, string given'];
        yield 'admin without a code' => [self::trip(['legs' => [self::leg(['summary' => ['length' => 1.0, 'admins' => [['country_code' => 7]]]])]]), 'trip.legs[0].summary.admins[0].country_code, int given'];
        yield 'crossing index not an integer' => [self::trip(['legs' => [self::leg(['summary' => ['length' => 1.0, 'admins' => [['country_code' => 'SK']], 'admin_crossings' => [['from_admin_index' => 0, 'to_admin_index' => 0, 'begin_shape_index' => 0.5, 'end_shape_index' => 1]]]])]]), 'An integer was expected at trip.legs[0].summary.admin_crossings[0].begin_shape_index, float given'];
        yield 'crossing outside the shape' => [self::trip(['legs' => [self::leg(['summary' => ['length' => 1.0, 'admins' => [['country_code' => 'SK'], ['country_code' => 'HU']], 'admin_crossings' => [['from_admin_index' => 0, 'to_admin_index' => 1, 'begin_shape_index' => 2, 'end_shape_index' => 9]]]])]]), 'The shape index 9 is outside the shape of 3 points'];
        yield 'maneuver without indexes' => [self::trip(['legs' => [self::leg(['maneuvers' => [['toll' => true]]])]]), 'An integer was expected at trip.legs[0].maneuvers[0].begin_shape_index, null given'];
        yield 'maneuvers not a list' => [self::trip(['legs' => [self::leg(['maneuvers' => 'none'])]]), 'A list was expected at trip.legs[0].maneuvers, string given'];
    }

    /**
     * A trip of one leg of three points on the equator in Slovakia, the middle maneuver tolled,
     * with some of its keys replaced.
     *
     * @param array<string, mixed> $replace
     */
    private static function trip(array $replace = [], bool $topLevel = false): string
    {
        $trip = [
            'legs' => [self::leg()],
            'summary' => ['length' => 2.0, 'time' => 100.0],
            'units' => 'kilometers',
        ];
        $answer = ['trip' => $topLevel ? $trip : array_merge($trip, $replace)];
        if ($topLevel) {
            $answer = array_merge($answer, $replace);
        }

        return json_encode($answer, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @param array<string, mixed> $replace
     *
     * @return array<string, mixed>
     */
    private static function leg(array $replace = []): array
    {
        return array_merge([
            // (0, 0), (0, 0.01), (0, 0.02) in polyline6
            'shape' => '???_pR?_pR',
            'summary' => ['length' => 2.0, 'admins' => [['country_code' => 'SK']], 'admin_crossings' => []],
            'maneuvers' => [['begin_shape_index' => 0, 'end_shape_index' => 1], ['begin_shape_index' => 1, 'end_shape_index' => 2, 'toll' => true]],
        ], $replace);
    }

    /**
     * @param list<CountrySegment> $segments
     *
     * @return list<array{string, int}>
     */
    private function segments(array $segments): array
    {
        return array_map(static fn (CountrySegment $s): array => [$s->country, $s->distanceMeters], $segments);
    }
}
