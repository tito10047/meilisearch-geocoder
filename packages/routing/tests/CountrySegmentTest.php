<?php

namespace Tito10047\Routing\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\CountrySegment;
use Tito10047\Routing\Exception\InvalidArgumentException;

final class CountrySegmentTest extends TestCase
{
    public function testHoldsTheCountryAndTheDistance(): void
    {
        $segment = new CountrySegment('SK', 20110);

        $this->assertSame('SK', $segment->country);
        $this->assertSame(20110, $segment->distanceMeters);
    }

    public function testAcceptsAZeroDistance(): void
    {
        $this->assertSame(0, (new CountrySegment('AT', 0))->distanceMeters);
    }

    #[DataProvider('provideInvalidSegments')]
    public function testRejectsInvalidValues(string $country, int $distanceMeters): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CountrySegment($country, $distanceMeters);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function provideInvalidSegments(): iterable
    {
        yield 'lower case' => ['sk', 1];
        yield 'alpha-3' => ['SVK', 1];
        yield 'empty' => ['', 1];
        yield 'trailing new line' => ["SK\n", 1];
        yield 'negative distance' => ['SK', -1];
    }
}
