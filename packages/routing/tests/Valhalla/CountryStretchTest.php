<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Valhalla\CountryStretch;

final class CountryStretchTest extends TestCase
{
    public function testMeasuresItsLength(): void
    {
        $stretch = new CountryStretch('HU', 20110.5, 244937.5);

        $this->assertSame('HU', $stretch->country);
        $this->assertEqualsWithDelta(20110.5, $stretch->fromMeters, \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(244937.5, $stretch->toMeters, \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(224827.0, $stretch->lengthMeters(), \PHP_FLOAT_EPSILON);
    }

    public function testMayBeEmpty(): void
    {
        $this->assertEqualsWithDelta(0.0, (new CountryStretch('SK', 0.0, 0.0))->lengthMeters(), \PHP_FLOAT_EPSILON);
    }

    #[DataProvider('provideInvalidStretches')]
    public function testRejectsInvalidValues(string $country, float $from, float $to): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CountryStretch($country, $from, $to);
    }

    /**
     * @return iterable<string, array{string, float, float}>
     */
    public static function provideInvalidStretches(): iterable
    {
        yield 'lower case country' => ['hu', 0.0, 1.0];
        yield 'empty country' => ['', 0.0, 1.0];
        yield 'negative start' => ['HU', -1.0, 1.0];
        yield 'backwards' => ['HU', 2.0, 1.0];
        yield 'not a number' => ['HU', 0.0, \NAN];
        yield 'infinite' => ['HU', 0.0, \INF];
    }
}
