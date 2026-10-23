<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Costing;
use Tito10047\Routing\MatrixRequest;
use Tito10047\Routing\Tests\Fixtures\Fixture;
use Tito10047\Routing\Valhalla\MatrixRequestEncoder;

final class MatrixRequestEncoderTest extends TestCase
{
    #[DataProvider('provideFixtures')]
    public function testEncodesTheRequestsTheFixturesWereRecordedWith(string $name): void
    {
        $this->assertSame(Fixture::requestBody($name), (new MatrixRequestEncoder())->encode(Fixture::matrixRequest($name)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideFixtures(): iterable
    {
        foreach (array_keys(Fixture::matrixRequests()) as $name) {
            yield $name => [$name];
        }
    }

    public function testAsksForTheConciseAnswerInKilometres(): void
    {
        $body = $this->encode(new MatrixRequest(Fixture::points(Fixture::BRATISLAVA), Fixture::points(Fixture::TRNAVA, Fixture::NITRA), radiusMeters: 50));

        $this->assertSame([
            'sources' => [['lat' => 48.1486, 'lon' => 17.1077, 'radius' => 50]],
            'targets' => [
                ['lat' => 48.3774, 'lon' => 17.5872, 'radius' => 50],
                ['lat' => 48.3069, 'lon' => 18.0864, 'radius' => 50],
            ],
            'costing' => 'auto',
            'verbose' => false,
            'units' => 'kilometers',
        ], $body);
    }

    public function testAvoidsRoadsAsTheRouteDoes(): void
    {
        $body = $this->encode(new MatrixRequest(Fixture::points(Fixture::BRATISLAVA), Fixture::points(Fixture::TRNAVA), Costing::Truck, avoidTolls: true, avoidHighways: true));

        $this->assertSame('truck', $body['costing']);
        $this->assertSame(['truck' => ['use_tolls' => 0, 'use_highways' => 0]], $body['costing_options']);
    }

    /**
     * @return array<string, mixed>
     */
    private function encode(MatrixRequest $request): array
    {
        $body = json_decode((new MatrixRequestEncoder())->encode($request), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($body));

        /* @var array<string, mixed> $body */
        return $body;
    }
}
