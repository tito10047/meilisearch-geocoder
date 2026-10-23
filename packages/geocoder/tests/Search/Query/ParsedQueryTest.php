<?php

namespace Tito10047\Geocoder\Tests\Search\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Query\ParsedQuery;

final class ParsedQueryTest extends TestCase
{
    public function testKeepsTheValues(): void
    {
        $query = new ParsedQuery('Hlavná', '12');

        $this->assertSame('Hlavná', $query->text);
        $this->assertSame('12', $query->houseNumber);
        $this->assertNull((new ParsedQuery('Košice'))->houseNumber);
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
