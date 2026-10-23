<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Costing;
use Tito10047\Routing\RouteRequest;
use Tito10047\Routing\Tests\Fixtures\Fixture;
use Tito10047\Routing\Valhalla\RequestEncoder;

final class RequestEncoderTest extends TestCase
{
    #[DataProvider('provideFixtures')]
    public function testEncodesTheRequestsTheFixturesWereRecordedWith(string $name): void
    {
        $this->assertSame(Fixture::requestBody($name), (new RequestEncoder())->encode(Fixture::request($name)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFixtures(): iterable
    {
        foreach (array_keys(Fixture::requests()) as $name) {
            yield $name => [$name];
        }
    }

    public function testAsksForWhatTheParserReads(): void
    {
        $body = $this->encode(new RouteRequest(Fixture::points(Fixture::BRATISLAVA, Fixture::KOSICE)));

        $this->assertSame([
            'locations' => [
                ['lat' => 48.1486, 'lon' => 17.1077, 'radius' => 200],
                ['lat' => 48.7164, 'lon' => 21.2611, 'radius' => 200],
            ],
            'costing' => 'auto',
            'units' => 'kilometers',
            'directions_type' => 'maneuvers',
            'admin_crossings' => true,
            'shape_format' => 'polyline6',
        ], $body);
    }

    public function testAsksForAlternativesBetweenTwoPoints(): void
    {
        $body = $this->encode(new RouteRequest(Fixture::points(Fixture::BRATISLAVA, Fixture::KOSICE), alternatives: 3));

        $this->assertSame(2, $body['alternates']);
    }

    public function testDoesNotAskForAlternativesWithStops(): void
    {
        $body = $this->encode(new RouteRequest(Fixture::points(Fixture::BRATISLAVA, Fixture::STEPHANSPLATZ, Fixture::BRNO), alternatives: 3));

        $this->assertArrayNotHasKey('alternates', $body);
        $this->assertCount(3, $body['locations']);
    }

    public function testSendsTheRadiusOfEveryPoint(): void
    {
        $body = $this->encode(new RouteRequest(Fixture::points(Fixture::STEPHANSPLATZ, Fixture::BRATISLAVA), radiusMeters: 0));

        $this->assertSame([0, 0], array_column($body['locations'], 'radius'));
    }

    #[DataProvider('provideCostings')]
    public function testSendsTheCosting(Costing $costing, string $value): void
    {
        $body = $this->encode(new RouteRequest(Fixture::points(Fixture::BRATISLAVA, Fixture::KOSICE), costing: $costing));

        $this->assertSame($value, $body['costing']);
    }

    /**
     * @return iterable<string, array{Costing, string}>
     */
    public static function provideCostings(): iterable
    {
        yield 'car' => [Costing::Car, 'auto'];
        yield 'truck' => [Costing::Truck, 'truck'];
        yield 'bicycle' => [Costing::Bicycle, 'bicycle'];
        yield 'pedestrian' => [Costing::Pedestrian, 'pedestrian'];
    }

    /**
     * @param array<string, int> $options
     */
    #[DataProvider('provideAvoidances')]
    public function testAvoidsTollsAndHighwaysThroughTheCostingOptions(bool $avoidTolls, bool $avoidHighways, array $options): void
    {
        $body = $this->encode(new RouteRequest(Fixture::points(Fixture::BRATISLAVA, Fixture::KOSICE), costing: Costing::Truck, avoidTolls: $avoidTolls, avoidHighways: $avoidHighways));

        $this->assertSame(['truck' => $options], $body['costing_options']);
    }

    /**
     * @return iterable<string, array{bool, bool, array<string, int>}>
     */
    public static function provideAvoidances(): iterable
    {
        yield 'tolls' => [true, false, ['use_tolls' => 0]];
        yield 'highways' => [false, true, ['use_highways' => 0]];
        yield 'both' => [true, true, ['use_tolls' => 0, 'use_highways' => 0]];
    }

    /**
     * @return array<string, mixed>
     */
    private function encode(RouteRequest $request): array
    {
        $body = json_decode((new RequestEncoder())->encode($request), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($body);

        /* @var array<string, mixed> $body */
        return $body;
    }
}
