<?php

namespace Tito10047\Geocoder\Tests\HouseNumber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\StreetKey;

final class HouseNumberTest extends TestCase
{
    #[DataProvider('provideOrientations')]
    public function testDerivesTheOrientationNumber(string $number, ?string $orientation): void
    {
        $this->assertSame($orientation, (new HouseNumber(new StreetKey(1), $number, null, new Coordinates(0, 0)))->orientation);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function provideOrientations(): iterable
    {
        yield 'plain' => ['12', null];
        yield 'with letter' => ['12a', null];
        yield 'conscription/orientation' => ['606/42', '42'];
        yield 'orientation with letter' => ['606/42b', '42b'];
        yield 'two slashes' => ['1/2/3', null];
        yield 'trailing slash' => ['12/', null];
    }

    public function testRejectsAnEmptyNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HouseNumber(new StreetKey(1), ' ', null, new Coordinates(0, 0));
    }
}
