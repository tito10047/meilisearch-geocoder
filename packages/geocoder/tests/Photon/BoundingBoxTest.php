<?php

namespace Tito10047\Geocoder\Tests\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Photon\BoundingBox;

final class BoundingBoxTest extends TestCase
{
    public function testParsesAndOrdersTheCorners(): void
    {
        $this->assertEquals(new BoundingBox(17.0, 48.0, 17.2, 48.2), BoundingBox::fromString('17.2, 48 ,17,48.2'));
    }

    #[DataProvider('provideInvalidBoxes')]
    public function testRejectsAnInvalidBox(string $box): void
    {
        $this->expectException(InvalidArgumentException::class);

        BoundingBox::fromString($box);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidBoxes(): iterable
    {
        yield 'three numbers' => ['1,2,3'];
        yield 'five numbers' => ['1,2,3,4,5'];
        yield 'not a number' => ['1,2,x,4'];
        yield 'latitude out of range' => ['1,-91,2,3'];
        yield 'longitude out of range' => ['1,2,181,3'];
    }

    public function testConstructorRejectsSwappedCorners(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BoundingBox(18.0, 48.0, 17.0, 49.0);
    }

    public function testContainsItsEdges(): void
    {
        $box = new BoundingBox(17.0, 48.0, 18.0, 49.0);

        $this->assertTrue($box->contains(new Coordinates(48.5, 17.5)));
        $this->assertTrue($box->contains(new Coordinates(48.0, 18.0)));
        $this->assertFalse($box->contains(new Coordinates(49.1, 17.5)));
        $this->assertFalse($box->contains(new Coordinates(48.5, 16.9)));
    }
}
