<?php

namespace Tito10047\Geocoder\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\StreetKey;

final class StreetKeyTest extends TestCase
{
    public function testFromAddressMatchesThePrototype(): void
    {
        // the formula of _lab/scripts/convert.php
        $expected = (int) hexdec(substr(hash('xxh64', 'AT|Wien|Stephansplatz'), 0, 15));

        $this->assertSame($expected, StreetKey::fromAddress('AT', 'Wien', 'Stephansplatz')->value);
    }

    public function testFromAddressIgnoresTheCaseOfTheCountry(): void
    {
        $this->assertEquals(StreetKey::fromAddress('AT', 'Wien', 'Stephansplatz'), StreetKey::fromAddress('at', 'Wien', 'Stephansplatz'));
    }

    public function testDocumentIdRoundTrips(): void
    {
        $key = StreetKey::fromAddress('SK', 'Košice', 'Hlavná');

        $this->assertSame('s'.$key->value, $key->documentId());
        $this->assertEquals($key, StreetKey::fromDocumentId($key->documentId()));
    }

    public function testAcceptsTheLargestKey(): void
    {
        $this->assertSame(0xFFFFFFFFFFFFFFF, StreetKey::fromDocumentId('s'. 0xFFFFFFFFFFFFFFF)->value);
    }

    #[DataProvider('provideInvalidDocumentIds')]
    public function testRejectsInvalidDocumentIds(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);

        StreetKey::fromDocumentId($id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidDocumentIds(): iterable
    {
        yield 'OSM object' => ['R109166'];
        yield 'no digits' => ['s'];
        yield 'leading zero' => ['s01'];
        yield 'trailing text' => ['s12#3'];
        yield 'trailing newline' => ["s12\n"];
        yield 'above 60 bits' => ['s'.(0xFFFFFFFFFFFFFFF + 1)];
        yield 'above 64 bits' => ['s99999999999999999999'];
    }

    public function testRejectsNegativeValues(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StreetKey(-1);
    }
}
