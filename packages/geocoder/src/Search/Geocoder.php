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
use Tito10047\Geocoder\StreetKey;

/**
 * The geocoder: the search engine finds the documents by name, the database adds the house
 * numbers, the reranker prefers the country of the user and the label formatter writes the lines.
 *
 * suggest(), step by step:
 *  1. the text is parsed: whitespace normalized, the house number taken out ("Hlavná 12 Koš" searches
 *     "Hlavná Koš" and the number 12); under 2 characters nothing is searched;
 *  2. the search engine is asked for the documents of the requested countries and kinds (an
 *     address is a street document): $limit of them, or $rerankWindow when a country is preferred,
 *     so that the reranker has candidates to promote;
 *  3. with a house number, the matching numbers of every street found become addresses, put right
 *     before their street; when no street is found at all, the number is a part of the name
 *     ("Praha 11", "Wien 3") and the whole text is searched again;
 *  4. every suggestion is named in the requested language (the translations of the document, the
 *     parts of towns, towns, counties and regions from the name catalog), labelled and given how
 *     many words of the searched text (without the house number) its document matched; an
 *     address matched as its street;
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

        $hits = $this->hits($query->text, $options);
        if (null !== $query->houseNumber && !$this->hasStreet($hits)) {
            // no street to take the number: it belongs to the name ("Praha 11", "Wien 3")
            $query = new ParsedQuery($query->fullText);
            $hits = $this->hits($query->text, $options);
        }

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
     * @return list<SearchHit>
     */
    private function hits(string $text, SearchOptions $options): array
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

        return $this->backend->search(new BackendQuery($text, $limit, $options->countries, array_values($kinds)));
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
