<?php

namespace Tito10047\Geocoder\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

final class CoordinatesTest extends TestCase
{
    public function testKeepsValidValues(): void
    {
        $coordinates = new Coordinates(48.2083537, 16.3725042);

        $this->assertEqualsWithDelta(48.2083537, $coordinates->latitude, \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(16.3725042, $coordinates->longitude, \PHP_FLOAT_EPSILON);
    }

    public function testAcceptsBounds(): void
    {
        $this->assertSame(-90.0, (new Coordinates(-90.0, -180.0))->latitude);
        $this->assertEqualsWithDelta(180.0, (new Coordinates(90.0, 180.0))->longitude, \PHP_FLOAT_EPSILON);
    }

    #[DataProvider('provideInvalidValues')]
    public function testRejectsInvalidValues(float $latitude, float $longitude, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new Coordinates($latitude, $longitude);
    }

    /**
     * @return iterable<string, array{float, float, string}>
     */
    public static function provideInvalidValues(): iterable
    {
        yield 'latitude too low' => [-90.1, 0.0, 'latitude'];
        yield 'latitude too high' => [90.1, 0.0, 'latitude'];
        yield 'latitude not a number' => [\NAN, 0.0, 'latitude'];
        yield 'longitude too low' => [0.0, -180.1, 'longitude'];
        yield 'longitude too high' => [0.0, 180.1, 'longitude'];
        yield 'longitude infinite' => [0.0, \INF, 'longitude'];
    }
}
