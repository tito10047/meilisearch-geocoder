<?php

namespace Tito10047\Geocoder\Tests\Search\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Query\ParsedQuery;
use Tito10047\Geocoder\Search\Query\RecognizedCountry;

final class RecognizedCountryTest extends TestCase
{
    public function testKeepsTheValues(): void
    {
        $literal = new ParsedQuery('Kosice Slovakia');
        $country = new RecognizedCountry(['SK'], 'Slovakia', $literal, 'Kosice');

        $this->assertSame(['SK'], $country->countries);
        $this->assertSame('Slovakia', $country->name);
        $this->assertSame($literal, $country->literal);
        $this->assertSame('Kosice', $country->before);
        $this->assertSame('', $country->after);
        $this->assertFalse($country->standsAlone());
        $this->assertFalse((new RecognizedCountry(['SK'], 'Slovakia', $literal, after: 'Kosice'))->standsAlone());
        $this->assertTrue((new RecognizedCountry(['SK'], 'Slovensko', new ParsedQuery('Košice Slovensko')))->standsAlone());
    }

    /**
     * @param array<array-key, mixed> $countries
     */
    #[DataProvider('provideInvalid')]
    public function testRejectsInvalidValues(array $countries, string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        /* @phpstan-ignore argument.type */
        new RecognizedCountry($countries, $name, new ParsedQuery('Košice Slovensko'));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function provideInvalid(): iterable
    {
        yield 'no country' => [[], 'Slovensko'];
        yield 'not a list' => [['x' => 'SK'], 'Slovensko'];
        yield 'lower case' => [['sk'], 'Slovensko'];
        yield 'alpha-3' => [['SVK'], 'Slovensko'];
        yield 'not a string' => [[42], 'Slovensko'];
        yield 'blank name' => [['SK'], ' '];
    }
}
