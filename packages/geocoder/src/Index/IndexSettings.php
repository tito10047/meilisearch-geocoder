<?php

namespace Tito10047\Geocoder\Index;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * The settings of the Meilisearch index of the geocoder.
 *
 * The defaults are the tuned settings of the research (variant D): the names and the towns, with
 * their translations, are searched; the custom rule "rank:desc" comes before "attribute" and
 * "exactness" so that a big town wins over a hamlet of a similar name. Changing the searchable or
 * filterable attributes of a filled index re-indexes it: the settings are applied before the
 * first document.
 */
final readonly class IndexSettings
{
    private const BUILT_IN_RANKING_RULES = ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness', 'attributeRank', 'wordPosition'];

    /**
     * @param list<string> $searchableAttributes searched, by decreasing priority
     * @param list<string> $displayedAttributes  returned in the hits ("*" for all)
     * @param list<string> $filterableAttributes filtered by equality only ("cc = SK"), no facet search
     * @param list<string> $sortableAttributes   usable in "sort"; the ranking does not need any
     * @param list<string> $rankingRules         built-in rules and custom ones ("rank:desc")
     * @param bool         $typoDisableOnNumbers numbers (postcodes, "Praha 11") must match exactly
     * @param int          $maxTotalHits         the most hits a search can return; an autocomplete needs few
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public array $searchableAttributes = ['names', 'cities'],
        public array $displayedAttributes = ['id', 'kind', 'type', 'name', 'names_i18n', 'suburb', 'city', 'county', 'region', 'cc', 'postcode', '_geo', 'rank'],
        public array $filterableAttributes = ['cc', 'kind'],
        public array $sortableAttributes = [],
        public array $rankingRules = ['words', 'typo', 'proximity', 'rank:desc', 'attribute', 'exactness'],
        public ProximityPrecision $proximityPrecision = ProximityPrecision::ByAttribute,
        public PrefixSearch $prefixSearch = PrefixSearch::IndexingTime,
        public bool $facetSearch = false,
        public bool $typoDisableOnNumbers = true,
        public int $maxTotalHits = 20,
    ) {
        $this->assertAttributes('searchable', $searchableAttributes, true, true);
        $this->assertAttributes('displayed', $displayedAttributes, true, true);
        $this->assertAttributes('filterable', $filterableAttributes, false, false);
        $this->assertAttributes('sortable', $sortableAttributes, false, false);

        if (!array_is_list($rankingRules)) {
            throw new InvalidArgumentException('The ranking rules must be a list.');
        }
        if ([] === $rankingRules) {
            throw new InvalidArgumentException('The ranking rules must not be empty.');
        }
        foreach ($rankingRules as $rule) {
            if (!\is_string($rule) || (!\in_array($rule, self::BUILT_IN_RANKING_RULES, true) && 1 !== preg_match('/^[A-Za-z0-9_.-]+:(?:asc|desc)$/D', $rule))) {
                throw new InvalidArgumentException(\sprintf('"%s" is not a valid ranking rule: use one of "%s" or "<attribute>:asc|desc".', \is_string($rule) ? $rule : get_debug_type($rule), implode('", "', self::BUILT_IN_RANKING_RULES)));
            }
        }
        if (\count($rankingRules) !== \count(array_unique($rankingRules))) {
            throw new InvalidArgumentException('The ranking rules must not repeat.');
        }
        if ($maxTotalHits < 1) {
            throw new InvalidArgumentException(\sprintf('The maximum number of hits must be positive, %d given.', $maxTotalHits));
        }
    }

    /**
     * Reads the settings returned by Meilisearch (GET /indexes/{uid}/settings); the settings this
     * class does not manage are ignored.
     *
     * @param array<array-key, mixed> $settings
     *
     * @throws InvalidArgumentException when the settings cannot be represented by this class
     */
    public static function fromArray(array $settings): self
    {
        $filterable = [];
        foreach (self::list($settings, 'filterableAttributes') as $rule) {
            $equalityOnly = [
                'attributePatterns' => $rule['attributePatterns'] ?? null,
                'features' => ['facetSearch' => false, 'filter' => ['equality' => true, 'comparison' => false]],
            ];
            if (!\is_array($rule) || !\is_array($rule['attributePatterns'] ?? null) || $rule != $equalityOnly) {
                throw new InvalidArgumentException('Only filterable attributes with equality filters and no facet search are supported.');
            }
            foreach ($rule['attributePatterns'] as $pattern) {
                $filterable[] = $pattern;
            }
        }

        $proximityPrecision = ProximityPrecision::tryFrom(self::string($settings, 'proximityPrecision'));
        $prefixSearch = PrefixSearch::tryFrom(self::string($settings, 'prefixSearch'));
        if (null === $proximityPrecision || null === $prefixSearch) {
            throw new InvalidArgumentException('The settings have an unknown "proximityPrecision" or "prefixSearch".');
        }

        $typoTolerance = $settings['typoTolerance'] ?? null;
        $pagination = $settings['pagination'] ?? null;
        $facetSearch = $settings['facetSearch'] ?? null;
        if (!\is_array($typoTolerance) || !\is_bool($typoTolerance['disableOnNumbers'] ?? null)
            || !\is_array($pagination) || !\is_int($pagination['maxTotalHits'] ?? null)
            || !\is_bool($facetSearch)) {
            throw new InvalidArgumentException('The settings miss "typoTolerance.disableOnNumbers", "pagination.maxTotalHits" or "facetSearch".');
        }

        return new self(
            searchableAttributes: self::strings($settings, 'searchableAttributes'),
            displayedAttributes: self::strings($settings, 'displayedAttributes'),
            filterableAttributes: self::strings(['f' => $filterable], 'f'),
            sortableAttributes: self::strings($settings, 'sortableAttributes'),
            rankingRules: self::strings($settings, 'rankingRules'),
            proximityPrecision: $proximityPrecision,
            prefixSearch: $prefixSearch,
            facetSearch: $facetSearch,
            typoDisableOnNumbers: $typoTolerance['disableOnNumbers'],
            maxTotalHits: $pagination['maxTotalHits'],
        );
    }

    /**
     * The settings as the body of PATCH /indexes/{uid}/settings.
     *
     * @return array{searchableAttributes: list<string>, displayedAttributes: list<string>, filterableAttributes: list<array{attributePatterns: list<string>, features: array{facetSearch: false, filter: array{equality: true, comparison: false}}}>, sortableAttributes: list<string>, rankingRules: list<string>, proximityPrecision: string, prefixSearch: string, facetSearch: bool, typoTolerance: array{disableOnNumbers: bool}, pagination: array{maxTotalHits: int}}
     */
    public function toArray(): array
    {
        return [
            'searchableAttributes' => $this->searchableAttributes,
            'displayedAttributes' => $this->displayedAttributes,
            'filterableAttributes' => [] === $this->filterableAttributes ? [] : [[
                'attributePatterns' => $this->filterableAttributes,
                'features' => ['facetSearch' => false, 'filter' => ['equality' => true, 'comparison' => false]],
            ]],
            'sortableAttributes' => $this->sortableAttributes,
            'rankingRules' => $this->rankingRules,
            'proximityPrecision' => $this->proximityPrecision->value,
            'prefixSearch' => $this->prefixSearch->value,
            'facetSearch' => $this->facetSearch,
            'typoTolerance' => ['disableOnNumbers' => $this->typoDisableOnNumbers],
            'pagination' => ['maxTotalHits' => $this->maxTotalHits],
        ];
    }

    /**
     * A stable fingerprint of the settings (SHA-256, hex): equal settings have an equal hash, so
     * an artifact or a status can tell whether an index was built with the expected settings.
     */
    public function hash(): string
    {
        return hash('sha256', json_encode($this->toArray(), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<array-key, mixed> $attributes
     */
    private function assertAttributes(string $what, array $attributes, bool $required, bool $wildcard): void
    {
        if (!array_is_list($attributes)) {
            throw new InvalidArgumentException(\sprintf('The %s attributes must be a list.', $what));
        }
        if ($required && [] === $attributes) {
            throw new InvalidArgumentException(\sprintf('The %s attributes must not be empty.', $what));
        }
        foreach ($attributes as $attribute) {
            if (!\is_string($attribute) || 1 !== preg_match('/^[^\s"\']+$/uD', $attribute)) {
                throw new InvalidArgumentException(\sprintf('The %s attributes must be names without spaces or quotes, "%s" given.', $what, \is_string($attribute) ? $attribute : get_debug_type($attribute)));
            }
            if ('*' === $attribute && (!$wildcard || 1 !== \count($attributes))) {
                throw new InvalidArgumentException(\sprintf('The wildcard "*" can only stand alone in the %s attributes.', $what));
            }
        }
        if (\count($attributes) !== \count(array_unique($attributes))) {
            throw new InvalidArgumentException(\sprintf('The %s attributes must not repeat.', $what));
        }
    }

    /**
     * @param array<array-key, mixed> $settings
     *
     * @return list<mixed>
     */
    private static function list(array $settings, string $key): array
    {
        $value = $settings[$key] ?? null;
        if (!\is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException(\sprintf('The setting "%s" must be a list.', $key));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $settings
     *
     * @return list<string>
     */
    private static function strings(array $settings, string $key): array
    {
        $strings = [];
        foreach (self::list($settings, $key) as $value) {
            if (!\is_string($value)) {
                throw new InvalidArgumentException(\sprintf('The setting "%s" must be a list of strings.', $key));
            }
            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * @param array<array-key, mixed> $settings
     */
    private static function string(array $settings, string $key): string
    {
        $value = $settings[$key] ?? null;
        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('The setting "%s" must be a string.', $key));
        }

        return $value;
    }
}
