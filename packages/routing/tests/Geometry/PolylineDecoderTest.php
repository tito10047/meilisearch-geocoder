<?php

namespace Tito10047\Routing\Tests\Geometry;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Geometry\PolylineDecoder;
use Tito10047\Routing\Tests\Fixtures\Fixture;

final class PolylineDecoderTest extends TestCase
{
    public function testDecodesGooglesExampleAtPrecision5(): void
    {
        $points = (new PolylineDecoder(5))->decode('_p~iF~ps|U_ulLnnqC_mqNvxq`@');

        $this->assertSame([[38.5, -120.2], [40.7, -120.95], [43.252, -126.453]], $this->pairs($points));
    }

    public function testDecodesPolyline6(): void
    {
        // encoded independently of this decoder, with the reference algorithm
        $points = (new PolylineDecoder())->decode('cfwyzAgkcs_@h~gzGnmvg@`htqrCgvt``Gg`er_A~sal_H~fdtjD_oiivI_oiivI~~ssmT');

        $this->assertSame([
            [48.148594, 17.10714],
            [43.507325, 16.439772],
            [-33.86882, 151.209296],
            [0.0, 0.0],
            [-90.0, 180.0],
            [90.0, -180.0],
        ], $this->pairs($points));
    }

    public function testDecodesTheShapeOfARecordedRoute(): void
    {
        $response = json_decode(Fixture::responseBody('ba-split'), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($response);
        $shape = $response['trip']['legs'][0]['shape'] ?? null;
        $this->assertIsString($shape);

        $points = (new PolylineDecoder(6))->decode($shape);

        $this->assertCount(10008, $points);
        // the start and the end snapped to the roads near Bratislava and Split
        $this->assertSame([48.148594, 17.10714], $this->pairs($points)[0]);
        $this->assertSame([43.507325, 16.439772], $this->pairs($points)[10007]);
    }

    public function testDecodesAnEmptyStringToNoPoints(): void
    {
        $this->assertSame([], (new PolylineDecoder())->decode(''));
    }

    #[DataProvider('provideInvalidPolylines')]
    public function testRejectsAnInvalidPolyline(string $encoded): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PolylineDecoder())->decode($encoded);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPolylines(): iterable
    {
        yield 'ends in a value' => ['cfwyzAgkcs_'];
        yield 'latitude without longitude' => ['cfwyzA'];
        yield 'character below the alphabet' => ['cfwyzA gkcs_@'];
        yield 'character above the alphabet' => ["cfwyzA\x7Fgkcs_@"];
        yield 'value too large' => [str_repeat('~', 13).'?'];
        yield 'latitude out of range' => ['_keqlD?'];
    }

    #[DataProvider('provideInvalidPrecisions')]
    public function testRejectsAnInvalidPrecision(int $precision): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PolylineDecoder($precision);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideInvalidPrecisions(): iterable
    {
        yield 'zero' => [0];
        yield 'too many' => [11];
    }

    /**
     * @param list<Coordinates> $points
     *
     * @return list<array{float, float}>
     */
    private function pairs(array $points): array
    {
        return array_map(static fn (Coordinates $point): array => [$point->latitude, $point->longitude], $points);
    }
}
