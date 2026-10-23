<?php

namespace Tito10047\Geocoder\Photon;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\SearchOptions;

/**
 * A search of the Photon API ("/api?q=…"), validated like Photon validates it.
 *
 * Every parameter of Photon 1.3 is accepted; an unknown one is an error, as in Photon. What the
 * geocoder cannot do is accepted and ignored: the location bias ("lat", "lon", "zoom",
 * "location_bias_scale"), "dedupe" (the import deduplicates), "geometry" (the features are
 * points), "debug" and "suggest_addresses". A search without a text ("include" categories only)
 * is not supported.
 *
 * @see https://github.com/komoot/photon/blob/master/docs/api-v1.md#search
 */
final readonly class PhotonQuery
{
    /**
     * The parameters of /api in Photon 1.3.
     */
    public const PARAMETERS = ['q', 'lang', 'limit', 'lat', 'lon', 'zoom', 'location_bias_scale', 'bbox', 'countrycode',
        'osm_tag', 'layer', 'include', 'exclude', 'dedupe', 'geometry', 'debug', 'suggest_addresses'];

    /**
     * The "lang" of the local names.
     */
    public const DEFAULT_LANGUAGE = 'default';

    private const LOCALE = '/^[a-z]{2,3}(?:[_-][A-Za-z0-9]{2,8})*$/D';

    /**
     * @param string               $text        the searched text
     * @param string|null          $locale      the language of the names ("sk", "sk_SK"); null for the local names
     * @param int<1, 20>|null      $limit       the number of features; null for the default of the geocoder
     * @param list<string>         $countries   ISO 3166-1 alpha-2, upper case: only these countries; [] for all
     * @param list<Layer>          $layers      only these layers; [] for all
     * @param list<OsmTagFilter>   $osmTags     the inclusions (any) and the exclusions (none) by the main OSM tag
     * @param list<CategoryFilter> $includes    each must match some category of a feature
     * @param list<CategoryFilter> $excludes    a feature is dropped when one matches all its categories
     * @param BoundingBox|null     $boundingBox only the features in this area
     * @param Coordinates|null     $focus       the point of the location bias: accepted, not used
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public string $text,
        public ?string $locale = null,
        public ?int $limit = null,
        public array $countries = [],
        public array $layers = [],
        public array $osmTags = [],
        public array $includes = [],
        public array $excludes = [],
        public ?BoundingBox $boundingBox = null,
        public ?Coordinates $focus = null,
    ) {
        if ('' === trim($text)) {
            throw new InvalidArgumentException('The parameter "q" is required: a search by categories only is not supported.');
        }
        if (null !== $locale && 1 !== preg_match(self::LOCALE, $locale)) {
            throw new InvalidArgumentException(\sprintf('The language must be an ISO 639 code ("sk", "sk_SK") or "%s", "%s" given.', self::DEFAULT_LANGUAGE, $locale));
        }
        if (null !== $limit && ($limit < 1 || $limit > SearchOptions::MAX_LIMIT)) {
            throw new InvalidArgumentException(\sprintf('The limit must be between 1 and %d, %d given.', SearchOptions::MAX_LIMIT, $limit));
        }
        $this->assertListOf($countries, 'string', 'countries');
        foreach ($countries as $country) {
            if (1 !== preg_match('/^[A-Z]{2}$/D', $country)) {
                throw new InvalidArgumentException(\sprintf('A country code must be an ISO 3166-1 alpha-2 code, "%s" given.', $country));
            }
        }
        $this->assertListOf($layers, Layer::class, 'layers');
        $this->assertListOf($osmTags, OsmTagFilter::class, 'OSM tag filters');
        $this->assertListOf($includes, CategoryFilter::class, 'included categories');
        $this->assertListOf($excludes, CategoryFilter::class, 'excluded categories');
    }

    /**
     * Parses the query string of a request. The parameters "osm_tag", "layer", "countrycode",
     * "include" and "exclude" may repeat (they cannot be read from PHP's $_GET, which keeps the
     * last one); of the others the first is used, as in Photon.
     *
     * @param string      $queryString    the raw query string: "q=berlin&layer=city&layer=locality"
     * @param string|null $fallbackLocale the language when there is no "lang" (Photon uses the Accept-Language header); ignored when invalid
     *
     * @throws InvalidArgumentException when a parameter is unknown or invalid
     */
    public static function fromQueryString(string $queryString, ?string $fallbackLocale = null): self
    {
        $parameters = self::parse($queryString);
        foreach (array_keys($parameters) as $name) {
            if (!\in_array($name, self::PARAMETERS, true)) {
                throw new InvalidArgumentException(\sprintf("Unknown query parameter '%s'.  Allowed parameters are: [%s]", $name, implode(', ', self::PARAMETERS)));
            }
        }
        $first = static fn (string $name): ?string => $parameters[$name][0] ?? null;

        $locale = $first('lang');
        if (null === $locale) {
            $locale = null !== $fallbackLocale && 1 === preg_match(self::LOCALE, $fallbackLocale) ? $fallbackLocale : null;
        } elseif (self::DEFAULT_LANGUAGE === $locale) {
            $locale = null;
        }

        $limit = $first('limit');
        if (null !== $limit) {
            if (1 !== preg_match('/^[+-]?\d{1,9}$/D', $limit)) {
                throw new InvalidArgumentException(\sprintf('The limit must be an integer, "%s" given.', $limit));
            }
            // like Photon: a limit below one returns one feature, a limit over the maximum the maximum
            $limit = max(1, min(SearchOptions::MAX_LIMIT, (int) $limit));
        }

        $layers = [];
        foreach ($parameters['layer'] ?? [] as $name) {
            $layers[$name] = Layer::tryFrom($name) ?? throw new InvalidArgumentException(\sprintf('Unknown layer type "%s". Available layers: [%s]', $name, Layer::names()));
        }

        $bbox = $first('bbox');
        self::number($first('zoom'), 'zoom');
        self::number($first('location_bias_scale'), 'location_bias_scale');

        return new self(
            $first('q') ?? '',
            $locale,
            $limit,
            array_values(array_unique(array_map(strtoupper(...), $parameters['countrycode'] ?? []))),
            array_values($layers),
            array_map(OsmTagFilter::fromString(...), $parameters['osm_tag'] ?? []),
            array_map(CategoryFilter::fromString(...), $parameters['include'] ?? []),
            array_map(CategoryFilter::fromString(...), $parameters['exclude'] ?? []),
            null === $bbox ? null : BoundingBox::fromString($bbox),
            self::focus($first('lat'), $first('lon')),
        );
    }

    /**
     * The kinds the layers allow; [] when no layer is asked for (every kind), null when the layers can hold nothing of the index.
     *
     * @return list<Kind>|null
     */
    public function kinds(): ?array
    {
        if ([] === $this->layers) {
            return [];
        }
        $kinds = [];
        foreach ($this->layers as $layer) {
            foreach ($layer->kinds() as $kind) {
                $kinds[$kind->value] = $kind;
            }
        }

        return [] === $kinds ? null : array_values($kinds);
    }

    /**
     * Whether a feature must be checked by matches(): the geocoder cannot filter by these itself.
     */
    public function filters(): bool
    {
        return [] !== $this->layers || [] !== $this->osmTags || [] !== $this->includes || [] !== $this->excludes || $this->boundingBox instanceof BoundingBox;
    }

    /**
     * Whether the feature passes the layers, the OSM tags, the categories and the bounding box.
     */
    public function matches(Feature $feature): bool
    {
        if ([] !== $this->layers && !\in_array($feature->layer, $this->layers, true)) {
            return false;
        }
        if ($this->boundingBox instanceof BoundingBox && !$this->boundingBox->contains($feature->coordinates)) {
            return false;
        }
        $included = null;
        foreach ($this->osmTags as $filter) {
            if ($filter->excludes) {
                if ($filter->matches($feature)) {
                    return false;
                }
            } else {
                $included = true === $included || $filter->matches($feature);
            }
        }
        if (false === $included) {
            return false;
        }
        foreach ($this->includes as $filter) {
            if (!$filter->matchesAny($feature)) {
                return false;
            }
        }
        foreach ($this->excludes as $filter) {
            if ($filter->matchesAll($feature)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, non-empty-list<string>> the values of each parameter, in their order
     */
    private static function parse(string $queryString): array
    {
        $parameters = [];
        foreach (explode('&', ltrim($queryString, '?')) as $pair) {
            if ('' === $pair) {
                continue;
            }
            [$name, $value] = explode('=', $pair, 2) + [1 => ''];
            $parameters[urldecode($name)][] = urldecode($value);
        }

        return $parameters;
    }

    /**
     * @throws InvalidArgumentException when only one of the two is given, or one is not a coordinate
     */
    private static function focus(?string $latitude, ?string $longitude): ?Coordinates
    {
        if (null === $latitude && null === $longitude) {
            return null;
        }
        if (null === $latitude || null === $longitude) {
            throw new InvalidArgumentException(\sprintf("Missing parameter '%s'.", null === $latitude ? 'lat' : 'lon'));
        }

        return new Coordinates(self::number($latitude, 'lat'), self::number($longitude, 'lon'));
    }

    /**
     * @throws InvalidArgumentException when the value is not a number
     */
    private static function number(?string $value, string $name): float
    {
        if (null === $value) {
            return 0.0;
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException(\sprintf('The parameter "%s" must be a number, "%s" given.', $name, $value));
        }

        return (float) $value;
    }

    /**
     * @param array<mixed> $values
     *
     * @throws InvalidArgumentException when the values are not a list of the type
     */
    private function assertListOf(array $values, string $type, string $what): void
    {
        if (!array_is_list($values)) {
            throw new InvalidArgumentException(\sprintf('The %s must be a list.', $what));
        }
        foreach ($values as $value) {
            if ('string' === $type ? !\is_string($value) : !$value instanceof $type) {
                throw new InvalidArgumentException(\sprintf('The %s must be instances of %s.', $what, $type));
            }
        }
    }
}
