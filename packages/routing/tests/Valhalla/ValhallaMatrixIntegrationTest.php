<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Tests\Fixtures\Fixture;
use Tito10047\Routing\Valhalla\ValhallaMatrix;

/**
 * Asks a live Valhalla, the one at VALHALLA_URL, built from the 9 countries of the research
 * (resources/docker/README.md); skipped when the variable is not set.
 *
 * Tile data changes with every OpenStreetMap extract, so the distances are compared loosely.
 */
#[Group('integration')]
final class ValhallaMatrixIntegrationTest extends TestCase
{
    private ValhallaMatrix $matrix;

    protected function setUp(): void
    {
        $url = getenv('VALHALLA_URL');
        if (!\is_string($url) || '' === $url) {
            $this->markTestSkipped('Set VALHALLA_URL to the URL of a Valhalla server to run the integration tests.');
        }

        $this->matrix = new ValhallaMatrix($url);
    }

    public function testComputesAMatrixInSlovakia(): void
    {
        $matrix = $this->matrix->matrix(Fixture::matrixRequest('matrix-sk-2x3'));

        $expected = [[202_335, 214_558, 92_790], [150_949, 170_637, 48_870]];
        foreach ($expected as $source => $row) {
            foreach ($row as $target => $meters) {
                $this->assertEqualsWithDelta($meters, $matrix->distanceMeters($source, $target), 0.05 * $meters, "$source → $target");
                $this->assertGreaterThan(0, $matrix->durationSeconds($source, $target));
            }
        }
    }

    public function testComputesAMatrixAcrossBorders(): void
    {
        $matrix = $this->matrix->matrix(Fixture::matrixRequest('matrix-cross-border-all'));

        $this->assertEqualsWithDelta(65_685, $matrix->distanceMeters(0, 0), 5_000);
        $this->assertSame(0, $matrix->distanceMeters(1, 1));
    }

    public function testAPairLongerByRoadThanTheLimitHasNoRoute(): void
    {
        // Bratislava → Košice: 300 km in a straight line, 441 km by road, over the 400 km limit
        $matrix = $this->matrix->matrix(Fixture::matrixRequest('matrix-unreachable'));

        $this->assertNotNull($matrix->distanceMeters(0, 0));
        $this->assertNull($matrix->distanceMeters(0, 2));
    }

    public function testIsUnavailableForPointsTooFarApart(): void
    {
        $this->expectException(RoutingUnavailableException::class);
        $this->expectExceptionCode(154);

        $this->matrix->matrix(Fixture::matrixRequest('matrix-too-far'));
    }
}
