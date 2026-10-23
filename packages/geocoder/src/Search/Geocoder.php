<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\HouseNumber\HouseNumberStoreInterface;
use Tito10047\Geocoder\Name\NameCatalogInterface;
use Tito10047\Geocoder\Search\Backend\BackendQuery;
use Tito10047\Geocoder\Search\Backend\SearchBackendInterface;
use Tito10047\Geocoder\Search\Backend\SearchHit;
use Tito10047\Geocoder\Search\Query\ParsedQuery;
use Tito10047\Geocoder\Search\Query\QueryParser;
use Tito10047\Geocoder\Search\Query\QueryParserInterface;
use Tito10047\Geocoder\Search\Query\RecognizedCountry;
use Tito10047\Geocoder\Search\Text\NameNormalizer;
use Tito10047\Geocoder\Search\Text\NameNormalizerInterface;
use Tito10047\Geocoder\StreetKey;

/**
 * The geocoder: the search engine finds the documents by name, the database adds the house
 * numbers, the reranker prefers the country of the user and the label formatter writes the lines.
 *
 * suggest(), step by step:
 *  1. the text is parsed: whitespace normalized, the house number, the postcode and a country taken
 *     out ("Hlavná 12, 040 01 Koš, Slovensko" searches "Hlavná Koš" in Slovakia and the number 12, see
 *     QueryParser); under 2 characters nothing is searched;
 *  2. the search engine is asked for the documents of the requested countries and kinds (an
 *     address is a street document): $limit of them, or $rerankWindow when a country is preferred,
 *     so that the reranker has candidates to promote. A country typed restricts the search to it,
 *     within the requested countries (none of them: nothing is found). As a country name may be a
 *     part of a name ("Via Italia", "Hotel Austria", "Włochy" in Warszawa), the text is searched
 *     first with the name kept: when a document matching every word is named by the words typed
 *     around the country name and the name itself (with at least one of those words, or the name
 *     alone when it was typed alone between commas), that literal reading is kept;
 *  3. with a house number, the matching numbers of every street found become addresses, put right
 *     before their street; when no street is found at all, the number is a part of the name
 *     ("Praha 11", "Wien 3") and the whole text is searched again;
 *  4. every suggestion is named in the requested language (the translations of the document, the
 *     parts of towns, towns, counties and regions from the name catalog), labelled and given how
 *     many words of the searched text (without the house number, the postcode and the country) its
 *     document matched; an address matched as its street;
 *  5. the reranker reorders them (knowing the ranks of their documents); with
 *     SearchOptions::$completeMatchesOnly the partial matches are dropped then, and the first
 *     $limit are returned. The search engine ranks the documents matching more words first, so
 *     the complete matches are among the $limit (or $rerankWindow) documents fetched.
 */
final readonly class Geocoder implements GeocoderInterface
{
    public const DEFAULT_RERANK_WINDOW = 15;
    private const MIN_LENGTH = 2;

    private SuggestionFactory $suggestions;

    /**
     * @param positive-int $rerankWindow the number of documents fetched for the reranker when a country is preferred
     *
     * @throws InvalidArgumentException when the window is not positive
     */
    public function __construct(
        private SearchBackendInterface $backend,
        private HouseNumberStoreInterface $houseNumbers,
        NameCatalogInterface $catalog,
        private RerankerInterface $reranker,
        LabelFormatterInterface $labelFormatter,
        private QueryParserInterface $parser = new QueryParser(),
        private int $rerankWindow = self::DEFAULT_RERANK_WINDOW,
        private NameNormalizerInterface $normalizer = new NameNormalizer(),
    ) {
        if ($rerankWindow < 1) {
            throw new InvalidArgumentException(\sprintf('The rerank window must be positive, %d given.', $rerankWindow));
        }
        $this->suggestions = new SuggestionFactory($catalog, $labelFormatter);
    }

    /**
     * @throws InvalidArgumentException when the text is not valid UTF-8
     */
    public function suggest(string $text, ?SearchOptions $options = null): array
    {
        $options ??= new SearchOptions();
        $query = $this->parser->parse($text);
        if (!$query instanceof ParsedQuery || $this->length($query) < self::MIN_LENGTH) {
            return [];
        }

        [$query, $hits] = $this->search($query, $options);

        $candidates = [];
        foreach ($hits as $hit) {
            $document = $hit->document;
            $suggestion = $this->suggestions->fromDocument($document, $options->language())->withMatch($hit->match());
            if (null !== $query->houseNumber && DocumentKind::Street === $document->kind) {
                foreach ($this->addresses($document, $suggestion, $query->houseNumber, $options) as $address) {
                    $candidates[] = new RankedSuggestion($address, $document->rank, $hit->matchingWords);
                }
            }
            $candidates[] = new RankedSuggestion($suggestion, $document->rank, $hit->matchingWords);
        }

        $candidates = array_values(array_filter($candidates, static fn (RankedSuggestion $candidate): bool => self::isRequested($candidate->suggestion->kind, $options)));

        $reranked = $this->reranker->rerank($candidates, $options);
        if ($options->completeMatchesOnly) {
            $reranked = array_filter($reranked, static fn (RankedSuggestion $candidate): bool => $candidate->suggestion->isCompleteMatch());
        }

        return array_map(static fn (RankedSuggestion $candidate): Suggestion => $candidate->suggestion, \array_slice($reranked, 0, $options->limit));
    }

    /**
     * @throws InvalidArgumentException when the text is not valid UTF-8
     */
    public function geocode(string $text, ?SearchOptions $options = null): ?Suggestion
    {
        return $this->suggest($text, ($options ?? new SearchOptions())->withLimit(1))[0] ?? null;
    }

    /**
     * The hits of the query, or of its literal reading when the country typed is a part of a name.
     *
     * @return array{ParsedQuery, list<SearchHit>} the reading searched and its hits
     */
    private function search(ParsedQuery $query, SearchOptions $options): array
    {
        $country = $query->country;
        if ($country instanceof RecognizedCountry) {
            [$literal, $hits] = $this->searchReading($country->literal, $options);
            if ($this->namesAPlace($country, $hits)) {
                return [$literal, $hits];
            }
        }

        return $this->searchReading($query, $options);
    }

    /**
     * @return array{ParsedQuery, list<SearchHit>}
     */
    private function searchReading(ParsedQuery $query, SearchOptions $options): array
    {
        $countries = $this->countries($query, $options);
        if (null === $countries) {
            return [$query, []];
        }

        $hits = $this->hits($query->text, $countries, $options);
        if (null !== $query->houseNumber && !$this->hasStreet($hits)) {
            // no street to take the number: it belongs to the name ("Praha 11", "Wien 3")
            $query = $query->withoutHouseNumber();
            $hits = $this->hits($query->text, $countries, $options);
        }

        return [$query, $hits];
    }

    /**
     * The countries to search: those requested, restricted to the country typed.
     *
     * @return list<string>|null the countries, all when empty; null for none (the country typed is not requested)
     */
    private function countries(ParsedQuery $query, SearchOptions $options): ?array
    {
        $typed = $query->countries();
        if ([] === $typed) {
            return $options->countries;
        }
        if ([] === $options->countries) {
            return $typed;
        }
        $both = array_values(array_intersect($options->countries, $typed));

        return [] === $both ? null : $both;
    }

    /**
     * Whether a hit of the literal reading matching every word is named by the country name and
     * the words typed around it: "Via Italia", "Roma Hotel Italia" (the hotel), "Warszawa, Włochy"
     * (the district). Its name is a run of those words holding the country name and, unless the
     * country name was typed alone, at least one of the other words: "Penzion Slovakia" does not
     * name "Kosice Slovakia", nor "Austria Classic Hotel Wien" "Wien, Austria".
     *
     * @param list<SearchHit> $hits
     */
    private function namesAPlace(RecognizedCountry $country, array $hits): bool
    {
        $before = $this->words($country->before);
        $name = $this->words($country->name);
        $typed = [...$before, ...$name, ...$this->words($country->after)];
        $from = \count($before);
        $to = $from + \count($name);
        $minLength = $country->standsAlone() ? \count($name) : \count($name) + 1;

        foreach ($hits as $hit) {
            if (false === $hit->match()?->isComplete()) {
                continue;
            }
            foreach ([$hit->document->name, ...array_values($hit->document->namesI18n)] as $documentName) {
                $words = $this->words($documentName);
                $length = \count($words);
                if ($length < $minLength) {
                    continue;
                }
                // a run of $length typed words covering the country name: it starts at most at $from
                for ($start = max(0, $to - $length); $start <= $from && $start + $length <= \count($typed); ++$start) {
                    if (\array_slice($typed, $start, $length) === $words) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{N}]+/u', $this->normalizer->normalize($text), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * @param list<string> $countries
     *
     * @return list<SearchHit>
     */
    private function hits(string $text, array $countries, SearchOptions $options): array
    {
        $limit = null === $options->preferredCountry ? $options->limit : max($options->limit, $this->rerankWindow);
        $kinds = [];
        foreach ($options->kinds as $kind) {
            $documentKind = match ($kind) {
                Kind::Place => DocumentKind::Place,
                Kind::Street, Kind::Address => DocumentKind::Street,
                Kind::Poi => DocumentKind::Poi,
            };
            $kinds[$documentKind->value] = $documentKind;
        }

        return $this->backend->search(new BackendQuery($text, $limit, $countries, array_values($kinds)));
    }

    /**
     * The house numbers of the street matching the typed one, as addresses.
     *
     * @return list<Suggestion>
     */
    private function addresses(Document $street, Suggestion $streetSuggestion, string $number, SearchOptions $options): array
    {
        try {
            $streetKey = StreetKey::fromDocumentId($street->id);
        } catch (InvalidArgumentException) {
            // a street of an index built elsewhere, without house numbers
            return [];
        }

        return array_map(
            fn (HouseNumber $houseNumber): Suggestion => $this->suggestions->address($street, $streetSuggestion, $houseNumber, $options->language()),
            $this->houseNumbers->find($streetKey, $number, $options->limit),
        );
    }

    /**
     * Whether the kind was asked for; a street is kept with the addresses, as they are typed through it.
     */
    private static function isRequested(Kind $kind, SearchOptions $options): bool
    {
        return [] === $options->kinds
            || \in_array($kind, $options->kinds, true)
            || (Kind::Street === $kind && \in_array(Kind::Address, $options->kinds, true));
    }

    /**
     * @param list<SearchHit> $hits
     */
    private function hasStreet(array $hits): bool
    {
        foreach ($hits as $hit) {
            if (DocumentKind::Street === $hit->document->kind) {
                return true;
            }
        }

        return false;
    }

    private function length(ParsedQuery $query): int
    {
        return (int) preg_match_all('/./su', $query->text.(null === $query->houseNumber ? '' : ' '.$query->houseNumber));
    }
}
