<?php

namespace Tito10047\Geocoder\Tests\Search;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\RankedSuggestion;
use Tito10047\Geocoder\Search\Reranker;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

final class RerankerTest extends TestCase
{
    /**
     * @param list<array{string, string, string, int, 4?: Kind}> $candidates id, name, country, rank and kind (a village place by default)
     * @param list<string>                                       $expected   the ids in the new order
     */
    #[DataProvider('provideRankedCases')]
    public function testRerankRanked(array $candidates, ?string $preferredCountry, array $expected, int $maxRankGap = Reranker::DEFAULT_MAX_RANK_GAP): void
    {
        $ranked = array_map(fn (array $candidate): RankedSuggestion => new RankedSuggestion($this->suggestion($candidate[0], $candidate[1], $candidate[2], 'village', $candidate[4] ?? Kind::Place), $candidate[3]), $candidates);

        $reranked = (new Reranker($maxRankGap))->rerankRanked($ranked, new SearchOptions(preferredCountry: $preferredCountry));

        $this->assertSame($expected, array_map(static fn (RankedSuggestion $candidate): string => $candidate->suggestion->id, $reranked));
        foreach ($reranked as $candidate) {
            $this->assertContains($candidate, $ranked, 'The same objects are returned.');
        }
    }

    /**
     * @return iterable<string, array{0: list<array{string, string, string, int, 4?: Kind}>, 1: string|null, 2: list<string>, 3?: int}>
     */
    public static function provideRankedCases(): iterable
    {
        $bystre = [['CZ', 'Bystré', 'CZ', 70497], ['SK2', 'Rožňavské Bystré', 'SK', 50480], ['SK', 'Bystré', 'SK', 50487]];
        yield 'no preferred country, nothing moves' => [$bystre, null, ['CZ', 'SK2', 'SK']];
        yield 'home village beats a foreign town' => [$bystre, 'SK', ['SK', 'CZ', 'SK2']];
        yield 'the preferred country is the leader' => [$bystre, 'CZ', ['CZ', 'SK', 'SK2']];
        yield 'a country without a match, the group still gathers' => [$bystre, 'PL', ['CZ', 'SK', 'SK2']];

        $praha = [['CZ', 'Praha', 'CZ', 90790], ['SK', 'Praha', 'SK', 50402]];
        yield 'a home village does not beat a foreign city' => [$praha, 'SK', ['CZ', 'SK']];
        yield 'a gap exactly at the maximum is overtaken' => [[['CZ', 'Praha', 'CZ', 75000], ['SK', 'Praha', 'SK', 50000]], 'SK', ['SK', 'CZ']];
        yield 'a gap one above the maximum is not' => [[['CZ', 'Praha', 'CZ', 75001], ['SK', 'Praha', 'SK', 50000]], 'SK', ['CZ', 'SK']];
        yield 'a custom maximum gap' => [$praha, 'SK', ['SK', 'CZ'], 50000];
        yield 'a zero gap needs an equal rank' => [[['CZ', 'Bystré', 'CZ', 50000], ['SK', 'Bystré', 'SK', 50000]], 'SK', ['SK', 'CZ'], 0];

        yield 'names compared without diacritics and case' => [[['CZ', 'BYSTRE', 'CZ', 70000], ['X', 'Other', 'AT', 60000], ['SK', 'Bystré', 'SK', 50000]], 'SK', ['SK', 'CZ', 'X']];
        yield 'kinds are not mixed' => [[['CZ', 'Hlavná', 'CZ', 50000], ['SK', 'Hlavná', 'SK', 25000, Kind::Street]], 'SK', ['CZ', 'SK']];
        yield 'only the best home suggestion moves' => [
            [['AT', 'Hlavná', 'AT', 25000, Kind::Street], ['SK1', 'Hlavná', 'SK', 25000, Kind::Street], ['CZ', 'Hlavná', 'CZ', 25000, Kind::Street], ['SK2', 'Hlavná', 'SK', 25000, Kind::Street]],
            'SK',
            ['SK1', 'AT', 'CZ', 'SK2'],
        ];
        yield 'empty' => [[], 'SK', []];
    }

    /**
     * @param list<array{string, string, int|null}> $candidates id, country and matching words
     * @param list<string>                          $expected
     */
    #[DataProvider('provideMatchingWords')]
    public function testDoesNotOvertakeABetterMatch(array $candidates, array $expected): void
    {
        $ranked = array_map(fn (array $candidate): RankedSuggestion => new RankedSuggestion($this->suggestion($candidate[0], 'Hlavná', $candidate[1], 'street', Kind::Street), 25000, $candidate[2]), $candidates);

        $reranked = (new Reranker())->rerankRanked($ranked, new SearchOptions(preferredCountry: 'CZ'));

        $this->assertSame($expected, array_map(static fn (RankedSuggestion $candidate): string => $candidate->suggestion->id, $reranked));
    }

    /**
     * @return iterable<string, array{list<array{string, string, int|null}>, list<string>}>
     */
    public static function provideMatchingWords(): iterable
    {
        yield 'the leader matched more words' => [[['SK', 'SK', 2], ['CZ', 'CZ', 1]], ['SK', 'CZ']];
        yield 'as many words' => [[['SK', 'SK', 2], ['CZ', 'CZ', 2]], ['CZ', 'SK']];
        yield 'more words' => [[['SK', 'SK', 1], ['CZ', 'CZ', 2]], ['CZ', 'SK']];
        yield 'unknown for the leader' => [[['SK', 'SK', null], ['CZ', 'CZ', 1]], ['CZ', 'SK']];
        yield 'unknown for the home one' => [[['SK', 'SK', 2], ['CZ', 'CZ', null]], ['CZ', 'SK']];
    }

    /**
     * @param list<array{string, string, string}> $candidates id, country and type
     * @param list<string>                        $expected
     */
    #[DataProvider('provideUnrankedCases')]
    public function testRerankWithoutRanksComparesTheTypes(array $candidates, array $expected): void
    {
        $suggestions = array_map(fn (array $candidate): Suggestion => $this->suggestion($candidate[0], 'Bystré', $candidate[1], $candidate[2]), $candidates);

        $reranked = (new Reranker())->rerank($suggestions, new SearchOptions(preferredCountry: 'SK'));

        $this->assertSame($expected, array_map(static fn (Suggestion $suggestion): string => $suggestion->id, $reranked));
    }

    /**
     * @return iterable<string, array{list<array{string, string, string}>, list<string>}>
     */
    public static function provideUnrankedCases(): iterable
    {
        yield 'same type' => [[['CZ', 'CZ', 'village'], ['SK', 'SK', 'village']], ['SK', 'CZ']];
        yield 'different type' => [[['CZ', 'CZ', 'town'], ['SK', 'SK', 'village']], ['CZ', 'SK']];
    }

    public function testRerankWithoutAPreferredCountryKeepsTheOrder(): void
    {
        $suggestions = [$this->suggestion('CZ', 'Bystré', 'CZ', 'village'), $this->suggestion('SK', 'Bystré', 'SK', 'village')];

        $this->assertSame($suggestions, (new Reranker())->rerank($suggestions, new SearchOptions()));
    }

    public function testRejectsANegativeGap(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Reranker(-1);
    }

    private function suggestion(string $id, string $name, string $countryCode, string $type, Kind $kind = Kind::Place): Suggestion
    {
        return new Suggestion($id, $kind, $type, $name, $name, new Coordinates(48.0, 17.0), $countryCode);
    }
}
