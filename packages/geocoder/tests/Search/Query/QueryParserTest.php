<?php

namespace Tito10047\Geocoder\Tests\Search\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Query\ParsedQuery;
use Tito10047\Geocoder\Search\Query\QueryParser;
use Tito10047\Geocoder\Search\Query\RecognizedCountry;
use Tito10047\Geocoder\Tests\Fake\FakeCountryRecognizer;

final class QueryParserTest extends TestCase
{
    #[DataProvider('provideTexts')]
    public function testParses(string $text, string $expectedText, ?string $expectedHouseNumber): void
    {
        $query = (new QueryParser())->parse($text);

        $this->assertInstanceOf(ParsedQuery::class, $query);
        $this->assertSame($expectedText, $query->text);
        $this->assertSame($expectedHouseNumber, $query->houseNumber);
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function provideTexts(): iterable
    {
        yield 'place' => ['Košice', 'Košice', null];
        yield 'whitespace normalized' => ["  Banská \t Bystrica\n", 'Banská Bystrica', null];
        yield 'street and number' => ['Hlavná 12', 'Hlavná', '12'];
        yield 'number in the middle' => ['Hlavná 12 Koš', 'Hlavná Koš', '12'];
        yield 'number first' => ['12 Hlavná', 'Hlavná', '12'];
        yield 'letter' => ['Hlavná 12a', 'Hlavná', '12a'];
        yield 'upper case letter' => ['Hlavná 12B', 'Hlavná', '12B'];
        yield 'conscription and orientation' => ['Družstevná 606/42 Bystré', 'Družstevná Bystré', '606/42'];
        yield 'orientation with a letter' => ['Hlavná 372/42A', 'Hlavná', '372/42A'];
        yield 'comma' => ['Hlavná 12, Košice', 'Hlavná Košice', '12'];
        yield 'postcode taken out' => ['Bystré 094 34', 'Bystré', null];
        yield 'number before a postcode' => ['Hlavná 12 040 01 Košice', 'Hlavná Košice', '12'];
        yield 'postcode and number' => ['Hlavná 12a 040 01 Košice', 'Hlavná Košice', '12a'];
        yield 'digit groups in a row stay' => ['Hlavná 12 34 Košice', 'Hlavná 12 34 Košice', null];
        yield 'number alone' => ['12', '12', null];
        yield 'last number wins' => ['5 Hlavná 12', '5 Hlavná', '12'];
        yield 'two letters are not a number' => ['Hlavná 12ab', 'Hlavná 12ab', null];
        yield 'ordinal is not a number' => ['1. mája', '1. mája', null];
        yield 'three parts are not a number' => ['Hlavná 1/2/3', 'Hlavná 1/2/3', null];
    }

    #[DataProvider('providePostcodes')]
    public function testTakesThePostcodeOut(string $text, string $expectedText, ?string $expectedHouseNumber, ?string $expectedPostcode): void
    {
        $query = (new QueryParser())->parse($text);

        $this->assertInstanceOf(ParsedQuery::class, $query);
        $this->assertSame($expectedText, $query->text);
        $this->assertSame($expectedHouseNumber, $query->houseNumber);
        $this->assertSame($expectedPostcode, $query->postcode);
    }

    /**
     * @return iterable<string, array{string, string, string|null, string|null}>
     */
    public static function providePostcodes(): iterable
    {
        yield 'Slovak, after a comma' => ['Hlavná 12, 040 01 Košice', 'Hlavná Košice', '12', '040 01'];
        yield 'Slovak, after the town' => ['Košice 040 01', 'Košice', null, '040 01'];
        yield 'Slovak, a part of its own' => ['Hlavná 12, 040 01, Košice', 'Hlavná Košice', '12', '040 01'];
        yield 'Slovak, without a space, after a comma' => ['Hlavná 12, 04001 Košice', 'Hlavná Košice', '12', '04001'];
        yield 'five digits after a house number' => ['Hlavná 12 04001 Košice', 'Hlavná Košice', '12', '04001'];
        yield 'five digits first' => ['80331 München', 'München', null, '80331'];
        yield 'Austrian, after a comma' => ['Stephansplatz 1, 1010 Wien', 'Stephansplatz Wien', '1', '1010'];
        yield 'Austrian, after a house number' => ['Stephansplatz 1 1010 Wien', 'Stephansplatz Wien', '1', '1010'];
        yield 'Austrian, first' => ['1010 Wien', 'Wien', null, '1010'];
        yield 'Polish, anywhere' => ['Floriańska 3, 31-019 Kraków', 'Floriańska Kraków', '3', '31-019'];
        yield 'Polish, after the town' => ['Kraków 31-019', 'Kraków', null, '31-019'];
        yield 'four digits after a street are a house number' => ['Hlavná 1010', 'Hlavná', '1010', null];
        yield 'four digits between a street and a town are a house number' => ['Hlavná 1010 Košice', 'Hlavná Košice', '1010', null];
        yield 'four digits after a town are a house number' => ['Wien 1010', 'Wien', '1010', null];
        yield 'four digits before a number are no postcode' => ['1010 12', '1010 12', null, null];
        yield 'a postcode alone is searched' => ['040 01', '040 01', null, null];
        yield 'a Polish postcode alone is searched' => ['31-019', '31-019', null, null];
        yield 'six digits are no postcode' => ['Hlavná 1, 123456 Košice', 'Hlavná 1 123456 Košice', null, null];
        yield 'the first postcode only' => ['040 01 Košice 040 02', 'Košice 040 02', null, '040 01'];
    }

    /**
     * @param list<string> $expectedCountries
     */
    #[DataProvider('provideCountries')]
    public function testRecognizesACountry(string $text, string $expectedText, ?string $expectedHouseNumber, array $expectedCountries, string $expectedName, string $expectedBefore, string $expectedAfter, string $expectedLiteral): void
    {
        $query = (new QueryParser(new FakeCountryRecognizer()))->parse($text);

        $this->assertInstanceOf(ParsedQuery::class, $query);
        $this->assertSame($expectedText, $query->text);
        $this->assertSame($expectedHouseNumber, $query->houseNumber);
        $this->assertSame($expectedCountries, $query->countries());
        $this->assertInstanceOf(RecognizedCountry::class, $query->country);
        $this->assertSame($expectedName, $query->country->name);
        $this->assertSame($expectedBefore, $query->country->before);
        $this->assertSame($expectedAfter, $query->country->after);
        $this->assertSame($expectedLiteral, $query->country->literal->fullText);
        $this->assertSame([], $query->country->literal->countries());
    }

    /**
     * @return iterable<string, array{string, string, string|null, list<string>, string, string, string, string}>
     */
    public static function provideCountries(): iterable
    {
        yield 'last part' => ['Bratislava, Slovensko', 'Bratislava', null, ['SK'], 'Slovensko', '', '', 'Bratislava Slovensko'];
        yield 'first part' => ['Slovensko, Bratislava', 'Bratislava', null, ['SK'], 'Slovensko', '', '', 'Slovensko Bratislava'];
        yield 'case and spaces' => [' Praha ,  ČESKO ', 'Praha', null, ['CZ'], 'ČESKO', '', '', 'Praha ČESKO'];
        yield 'two words' => ['Praha, Česká  republika', 'Praha', null, ['CZ'], 'Česká republika', '', '', 'Praha Česká republika'];
        yield 'code in upper case' => ['Košice, SK', 'Košice', null, ['SK'], 'SK', '', '', 'Košice SK'];
        yield 'without a comma, at the end' => ['Kosice Slovakia', 'Kosice', null, ['SK'], 'Slovakia', 'Kosice', '', 'Kosice Slovakia'];
        yield 'without a comma, at the beginning' => ['Slovakia Kosice', 'Kosice', null, ['SK'], 'Slovakia', '', 'Kosice', 'Slovakia Kosice'];
        yield 'at the end of the last part' => ['Hlavná 12, Košice Slovensko', 'Hlavná Košice', '12', ['SK'], 'Slovensko', 'Košice', '', 'Hlavná 12 Košice Slovensko'];
        yield 'the longest name' => ['Sarajevo Bosna i Hercegovina', 'Sarajevo', null, ['BA'], 'Bosna i Hercegovina', 'Sarajevo', '', 'Sarajevo Bosna i Hercegovina'];
        yield 'with a house number and a postcode' => ['Hlavná 12, 040 01 Košice, Slovensko', 'Hlavná Košice', '12', ['SK'], 'Slovensko', '', '', 'Hlavná 12 Košice Slovensko'];
        yield 'a name of a street' => ['Via Italia', 'Via', null, ['IT'], 'Italia', 'Via', '', 'Via Italia'];
        yield 'a district' => ['Warszawa, Włochy', 'Warszawa', null, ['IT'], 'Włochy', '', '', 'Warszawa Włochy'];
    }

    #[DataProvider('provideNoCountries')]
    public function testRecognizesNoCountry(string $text, string $expectedText, ?string $expectedHouseNumber): void
    {
        $query = (new QueryParser(new FakeCountryRecognizer()))->parse($text);

        $this->assertInstanceOf(ParsedQuery::class, $query);
        $this->assertNull($query->country);
        $this->assertSame([], $query->countries());
        $this->assertSame($expectedText, $query->text);
        $this->assertSame($expectedHouseNumber, $query->houseNumber);
    }

    /**
     * @return iterable<string, array{string, string, string|null}>
     */
    public static function provideNoCountries(): iterable
    {
        yield 'alone' => ['Slovensko', 'Slovensko', null];
        yield 'alone with a comma' => ['Slovensko,', 'Slovensko', null];
        yield 'with a house number only' => ['Slovensko 12', 'Slovensko', '12'];
        yield 'with a postcode only' => ['040 01 Slovensko', 'Slovensko', null];
        yield 'a part of a word' => ['Slovenská Ľupča', 'Slovenská Ľupča', null];
        yield 'a part of a hyphenated word' => ['Rakúsko-uhorská', 'Rakúsko-uhorská', null];
        yield 'in the middle' => ['Piazza Italia Milano', 'Piazza Italia Milano', null];
        yield 'at the end of the first part' => ['Piazza Italia, Milano', 'Piazza Italia Milano', null];
        yield 'not before a house number' => ['Via Italia 5', 'Via Italia', '5'];
        yield 'the first part with a house number' => ['Polska 5, Kraków', 'Polska Kraków', '5'];
        yield 'a code in lower case' => ['Košice, sk', 'Košice sk', null];
        yield 'a code among words' => ['Košice SK', 'Košice SK', null];
        yield 'a code in the first part' => ['SK, Košice', 'SK Košice', null];
        yield 'an unknown code' => ['Košice, KE', 'Košice KE', null];
        yield 'an unknown country' => ['Paris, France', 'Paris France', null];
    }

    public function testRecognizesNoCountryWithoutARecognizer(): void
    {
        $query = (new QueryParser())->parse('Bratislava, Slovensko');

        $this->assertSame('Bratislava Slovensko', $query?->text);
        $this->assertNull($query->country);
    }

    public function testKeepsTheWholeTextWithoutTheCountryAndThePostcode(): void
    {
        $query = (new QueryParser(new FakeCountryRecognizer()))->parse('Praha 11, 110 00, Česko');

        $this->assertSame('Praha', $query?->text);
        $this->assertSame('11', $query->houseNumber);
        $this->assertSame('Praha 11', $query->fullText);
        $this->assertSame('110 00', $query->postcode);
        $this->assertSame(['CZ'], $query->countries());
    }

    public function testKeepsTheWholeText(): void
    {
        $this->assertSame('Wien 3 Landstraße', (new QueryParser())->parse(' Wien 3,  Landstraße')?->fullText);
        $this->assertSame('Košice', (new QueryParser())->parse('Košice ')?->fullText);
    }

    #[DataProvider('provideNothing')]
    public function testNothingToSearch(string $text): void
    {
        $this->assertNull((new QueryParser())->parse($text));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideNothing(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [" \t\n"];
        yield 'separators' => [' , ; '];
    }

    public function testRejectsInvalidUtf8(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new QueryParser())->parse("Ko\xC5");
    }
}
