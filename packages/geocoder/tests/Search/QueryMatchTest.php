<?php

namespace Tito10047\Geocoder\Tests\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\QueryMatch;

final class QueryMatchTest extends TestCase
{
    #[DataProvider('provideMatches')]
    public function testIsCompleteWhenEveryWordMatched(int $matchedWords, int $queryWords, bool $complete): void
    {
        $match = new QueryMatch($matchedWords, $queryWords);

        $this->assertSame($matchedWords, $match->matchedWords);
        $this->assertSame($queryWords, $match->queryWords);
        $this->assertSame($complete, $match->isComplete());
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function provideMatches(): iterable
    {
        yield 'one word' => [1, 1, true];
        yield 'every word' => [3, 3, true];
        yield 'a part of the words' => [2, 3, false];
        yield 'no word' => [0, 2, false];
    }

    #[DataProvider('provideInvalid')]
    public function testRejectsNumbersOutOfRange(int $matchedWords, int $queryWords): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueryMatch($matchedWords, $queryWords);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function provideInvalid(): iterable
    {
        yield 'no words in the query' => [0, 0];
        yield 'negative words in the query' => [0, -1];
        yield 'negative matched words' => [-1, 2];
        yield 'more matched words than words' => [3, 2];
    }
}
