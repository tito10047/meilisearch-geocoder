<?php

namespace Tito10047\Geocoder\Tests\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\RankedSuggestion;
use Tito10047\Geocoder\Search\Suggestion;

final class RankedSuggestionTest extends TestCase
{
    public function testKeepsTheValues(): void
    {
        $suggestion = $this->suggestion();
        $ranked = new RankedSuggestion($suggestion, 50487);

        $this->assertSame($suggestion, $ranked->suggestion);
        $this->assertSame(50487, $ranked->rank);
        $this->assertNull($ranked->matchingWords);
        $this->assertSame(2, (new RankedSuggestion($suggestion, 0, 2))->matchingWords);
    }

    #[DataProvider('provideInvalid')]
    public function testRejectsNegativeNumbers(int $rank, ?int $matchingWords): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RankedSuggestion($this->suggestion(), $rank, $matchingWords);
    }

    /**
     * @return iterable<string, array{int, int|null}>
     */
    public static function provideInvalid(): iterable
    {
        yield 'rank' => [-1, null];
        yield 'matching words' => [0, -1];
    }

    private function suggestion(): Suggestion
    {
        return new Suggestion('N26036911', Kind::Place, 'village', 'Bystré', 'Bystré, Slovensko', new Coordinates(49.0, 21.5), 'SK');
    }
}
