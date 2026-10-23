<?php

namespace Tito10047\Geocoder\Tests\Search\Backend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Backend\SearchHit;
use Tito10047\Geocoder\Search\QueryMatch;
use Tito10047\Geocoder\Tests\Fake\Documents;

final class SearchHitTest extends TestCase
{
    public function testKeepsTheValues(): void
    {
        $document = Documents::create('R1', 'Košice');

        $this->assertSame($document, (new SearchHit($document, 2))->document);
        $this->assertSame(2, (new SearchHit($document, 2))->matchingWords);
        $this->assertNull((new SearchHit($document))->matchingWords);
        $this->assertSame(3, (new SearchHit($document, 2, 3))->queryWords);
        $this->assertNull((new SearchHit($document, 2))->queryWords);
    }

    public function testTheMatchNeedsBothNumbers(): void
    {
        $document = Documents::create('R1', 'Košice');

        $this->assertEquals(new QueryMatch(2, 3), (new SearchHit($document, 2, 3))->match());
        $this->assertNull((new SearchHit($document, 2))->match());
        $this->assertNull((new SearchHit($document, null, 3))->match());
    }

    #[DataProvider('provideInvalidWords')]
    public function testRejectsInvalidNumbersOfWords(int $matchingWords, ?int $queryWords): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SearchHit(Documents::create('R1', 'Košice'), $matchingWords, $queryWords);
    }

    /**
     * @return iterable<string, array{int, int|null}>
     */
    public static function provideInvalidWords(): iterable
    {
        yield 'negative matching words' => [-1, null];
        yield 'no words in the query' => [0, 0];
        yield 'more matching words than words' => [3, 2];
    }
}
