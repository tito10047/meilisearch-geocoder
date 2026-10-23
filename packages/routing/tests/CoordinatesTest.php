<?php

namespace Tito10047\Routing\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Exception\InvalidArgumentException;

final class CoordinatesTest extends TestCase
{
    #[DataProvider('provideValidCoordinates')]
    public function testAcceptsValidCoordinates(float $latitude, float $longitude): void
    {
        $coordinates = new Coordinates($latitude, $longitude);

        $this->assertSame($latitude, $coordinates->latitude);
        $this->assertSame($longitude, $coordinates->longitude);
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function provideValidCoordinates(): iterable
    {
        yield 'Bratislava' => [48.1486, 17.1077];
        yield 'origin' => [0.0, 0.0];
        yield 'south-west corner' => [-90.0, -180.0];
        yield 'north-east corner' => [90.0, 180.0];
    }

    #[DataProvider('provideInvalidCoordinates')]
    public function testRejectsInvalidCoordinates(float $latitude, float $longitude): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Coordinates($latitude, $longitude);
    }

    /**
     * @return iterable<string, array{float, float}>
     */
    public static function provideInvalidCoordinates(): iterable
    {
        yield 'latitude too small' => [-90.1, 0.0];
        yield 'latitude too large' => [90.1, 0.0];
        yield 'longitude too small' => [0.0, -180.1];
        yield 'longitude too large' => [0.0, 180.1];
        yield 'latitude not a number' => [\NAN, 0.0];
        yield 'longitude infinite' => [0.0, \INF];
    }
}
