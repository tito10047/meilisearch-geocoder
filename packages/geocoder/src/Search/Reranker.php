<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Text\NameNormalizer;
use Tito10047\Geocoder\Search\Text\NameNormalizerInterface;

/**
 * Prefers the country of the user among the suggestions of the same name.
 *
 * The suggestions of the same kind and name (compared without diacritics and case) are gathered
 * at the place of the best of them, and the best one of the preferred country goes first — unless
 * the foreign leader has a rank higher by more than $maxRankGap: a home village then beats a
 * foreign small town ("bystre": Bystré SK before Bystré CZ), never a city ("praha" for a Slovak
 * user stays Prague, not the village Praha) — and unless the foreign leader matched more words of
 * the query: "hlavna kosice" for a Czech user stays the Hlavná of Košice, not a Czech Hlavná
 * found by its name alone. Nothing else moves. Without a preferred country the order is left as
 * it is.
 *
 * rerank() (without the ranks) cannot measure the gap: it lets the home suggestion jump only a
 * foreign one of the same type, a village over a village.
 */
final readonly class Reranker implements RankedRerankerInterface
{
    public const DEFAULT_MAX_RANK_GAP = 25000;

    /**
     * @param int $maxRankGap how much higher a foreign leader may rank and still be overtaken
     *
     * @throws InvalidArgumentException when the gap is negative
     */
    public function __construct(
        private int $maxRankGap = self::DEFAULT_MAX_RANK_GAP,
        private NameNormalizerInterface $normalizer = new NameNormalizer(),
    ) {
        if ($maxRankGap < 0) {
            throw new InvalidArgumentException(\sprintf('The maximum rank gap must not be negative, %d given.', $maxRankGap));
        }
    }

    public function rerank(array $suggestions, SearchOptions $options): array
    {
        $candidates = array_map(static fn (Suggestion $suggestion): array => [$suggestion, null, null], $suggestions);

        return array_map(static fn (int $i): Suggestion => $suggestions[$i], $this->reorder($candidates, $options));
    }

    public function rerankRanked(array $suggestions, SearchOptions $options): array
    {
        $candidates = array_map(static fn (RankedSuggestion $ranked): array => [$ranked->suggestion, $ranked->rank, $ranked->matchingWords], $suggestions);

        return array_map(static fn (int $i): RankedSuggestion => $suggestions[$i], $this->reorder($candidates, $options));
    }

    /**
     * @param list<array{Suggestion, int|null, int|null}> $candidates the suggestions, their ranks and matching words (null when unknown)
     *
     * @return list<int> the positions of the candidates in the new order
     */
    private function reorder(array $candidates, SearchOptions $options): array
    {
        $home = $options->preferredCountry;
        if (null === $home) {
            return array_keys($candidates);
        }

        /** @var array<string, list<int>> $groups in the order of their first suggestion */
        $groups = [];
        foreach ($candidates as $position => [$suggestion]) {
            $groups[$suggestion->kind->value."\0".$this->normalizer->normalize($suggestion->name)][] = $position;
        }

        $order = [];
        foreach ($groups as $group) {
            foreach ($group as $i => $position) {
                if ($candidates[$position][0]->countryCode !== $home) {
                    continue;
                }
                if ($i > 0 && $this->mayOvertake($candidates[$position], $candidates[$group[0]])) {
                    unset($group[$i]);
                    array_unshift($group, $position);
                }
                break;
            }
            array_push($order, ...$group);
        }

        return $order;
    }

    /**
     * @param array{Suggestion, int|null, int|null} $home
     * @param array{Suggestion, int|null, int|null} $leader
     */
    private function mayOvertake(array $home, array $leader): bool
    {
        if (null !== $home[2] && null !== $leader[2] && $home[2] < $leader[2]) {
            return false;
        }
        if (null === $home[1] || null === $leader[1]) {
            return $home[0]->type === $leader[0]->type;
        }

        return $leader[1] - $home[1] <= $this->maxRankGap;
    }
}
