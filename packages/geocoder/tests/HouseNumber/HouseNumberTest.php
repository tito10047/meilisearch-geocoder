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

    public function testKeepsTheSuburbAndTheReference(): void
    {
        $houseNumber = new HouseNumber(new StreetKey(1), '12', null, new Coordinates(0, 0), 'Staré Mesto', '7101722639');

        $this->assertSame('Staré Mesto', $houseNumber->suburb);
        $this->assertSame('7101722639', $houseNumber->reference);
        $this->assertTrue(HouseNumber::isValidReference(str_repeat('7', 32)));
        $this->assertFalse(HouseNumber::isValidReference(str_repeat('7', 33)));
        $this->assertFalse(HouseNumber::isValidReference(' '));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('provideInvalidParts')]
    public function testRejectsAnInvalidPart(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HouseNumber(new StreetKey(1), '12', null, new Coordinates(0, 0), ...$arguments);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidParts(): iterable
    {
        yield 'empty suburb' => [['suburb' => ' ']];
        yield 'empty reference' => [['reference' => '']];
        yield 'reference too long' => [['reference' => str_repeat('7', 33)]];
    }

    public function testRejectsAnEmptyNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HouseNumber(new StreetKey(1), ' ', null, new Coordinates(0, 0));
    }
}
