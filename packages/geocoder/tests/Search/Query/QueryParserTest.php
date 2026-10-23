<?php

namespace Tito10047\Geocoder\Tests\Search\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Query\QueryParser;

final class QueryParserTest extends TestCase
{
    #[DataProvider('provideTexts')]
    public function testParses(string $text, string $expectedText, ?string $expectedHouseNumber): void
    {
        $query = (new QueryParser())->parse($text);

        $this->assertInstanceOf(\Tito10047\Geocoder\Search\Query\ParsedQuery::class, $query);
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
        yield 'postcode stays' => ['Bystré 094 34', 'Bystré 094 34', null];
        yield 'digit groups in a row stay' => ['Hlavná 12 040 01 Košice', 'Hlavná 12 040 01 Košice', null];
        yield 'postcode and number' => ['Hlavná 12a 040 01 Košice', 'Hlavná 040 01 Košice', '12a'];
        yield 'number alone' => ['12', '12', null];
        yield 'last number wins' => ['5 Hlavná 12', '5 Hlavná', '12'];
        yield 'two letters are not a number' => ['Hlavná 12ab', 'Hlavná 12ab', null];
        yield 'ordinal is not a number' => ['1. mája', '1. mája', null];
        yield 'three parts are not a number' => ['Hlavná 1/2/3', 'Hlavná 1/2/3', null];
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
