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
 */
final readonly class Reranker implements RerankerInterface
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
        $home = $options->preferredCountry;
        if (null === $home) {
            return $suggestions;
        }

        /** @var array<string, list<RankedSuggestion>> $groups in the order of their first suggestion */
        $groups = [];
        foreach ($suggestions as $candidate) {
            $groups[$candidate->suggestion->kind->value."\0".$this->normalizer->normalize($candidate->suggestion->name)][] = $candidate;
        }

        $reranked = [];
        foreach ($groups as $group) {
            foreach ($group as $i => $candidate) {
                if ($candidate->suggestion->countryCode !== $home) {
                    continue;
                }
                if ($i > 0 && $this->mayOvertake($candidate, $group[0])) {
                    unset($group[$i]);
                    array_unshift($group, $candidate);
                }
                break;
            }
            array_push($reranked, ...$group);
        }

        return $reranked;
    }

    private function mayOvertake(RankedSuggestion $home, RankedSuggestion $leader): bool
    {
        if (null !== $home->matchingWords && null !== $leader->matchingWords && $home->matchingWords < $leader->matchingWords) {
            return false;
        }

        return $leader->rank - $home->rank <= $this->maxRankGap;
    }
}
