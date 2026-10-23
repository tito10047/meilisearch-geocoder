<?php

namespace Tito10047\Geocoder\Tests\Search\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Query\ParsedQuery;
use Tito10047\Geocoder\Search\Query\RecognizedCountry;

final class ParsedQueryTest extends TestCase
{
    public function testKeepsTheValues(): void
    {
        $query = new ParsedQuery('Hlavná', '12');

        $this->assertSame('Hlavná', $query->text);
        $this->assertSame('12', $query->houseNumber);
        $this->assertSame('Hlavná 12', $query->fullText);
        $this->assertNull((new ParsedQuery('Košice'))->houseNumber);
        $this->assertSame('Košice', (new ParsedQuery('Košice'))->fullText);
        $this->assertSame('12 Hlavná', (new ParsedQuery('Hlavná', '12', '12 Hlavná'))->fullText);
        $this->assertNull($query->postcode);
        $this->assertNull($query->country);
        $this->assertSame([], $query->countries());
    }

    public function testKeepsThePostcodeAndTheCountry(): void
    {
        $country = new RecognizedCountry(['SK'], 'Slovensko', new ParsedQuery('Hlavná Košice Slovensko', '12'));
        $query = new ParsedQuery('Hlavná Košice', '12', null, '040 01', $country);

        $this->assertSame('Hlavná Košice 12', $query->fullText);
        $this->assertSame('040 01', $query->postcode);
        $this->assertSame($country, $query->country);
        $this->assertSame(['SK'], $query->countries());
    }

    public function testKeepsThePostcodeAndTheCountryWithoutTheHouseNumber(): void
    {
        $country = new RecognizedCountry(['CZ'], 'Česko', new ParsedQuery('Praha Česko', '11', 'Praha 11 Česko'));
        $query = (new ParsedQuery('Praha', '11', 'Praha 11', '110 00', $country))->withoutHouseNumber();

        $this->assertSame('Praha 11', $query->text);
        $this->assertNull($query->houseNumber);
        $this->assertSame('Praha 11', $query->fullText);
        $this->assertSame('110 00', $query->postcode);
        $this->assertSame($country, $query->country);
    }

    #[DataProvider('provideInvalidPostcodes')]
    public function testRejectsAnEmptyPostcode(string $postcode): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParsedQuery('Košice', null, null, $postcode);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidPostcodes(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['  '];
    }

    #[DataProvider('provideInvalid')]
    public function testRejectsInvalidValues(string $text, ?string $houseNumber): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ParsedQuery($text, $houseNumber);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function provideInvalid(): iterable
    {
        yield 'empty text' => ['', null];
        yield 'blank text' => ['  ', '12'];
        yield 'empty house number' => ['Hlavná', ''];
        yield 'blank house number' => ['Hlavná', ' '];
    }
}
