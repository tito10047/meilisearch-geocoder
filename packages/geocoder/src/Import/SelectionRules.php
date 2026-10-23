<?php

namespace Tito10047\Geocoder\Import;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * What of a Photon dump becomes a document, and how it ranks.
 *
 * The rank of a document is its base rank × 1000 + its Nominatim importance (0 to 1) × 999, the
 * custom ranking rule "rank:desc" of the index: a city outranks a village, a village a street,
 * and among the same kind the more important wins (Roma 90 767, Rimini 90 639).
 */
final readonly class SelectionRules
{
    public const DEFAULT_PLACE_TYPES = [
        'city' => 90,
        'town' => 70,
        'village' => 50,
        'suburb' => 45,
        'borough' => 45,
        'quarter' => 40,
        'neighbourhood' => 30,
        'hamlet' => 30,
    ];

    /** Only what a trip can head for. */
    public const DEFAULT_POIS = [
        'tourism' => ['hotel', 'guest_house', 'hostel', 'motel', 'apartment', 'chalet', 'alpine_hut', 'camp_site',
            'caravan_site', 'attraction', 'museum', 'theme_park', 'zoo', 'aquarium'],
        'leisure' => ['water_park', 'stadium', 'resort', 'marina', 'golf_course'],
        'aeroway' => ['aerodrome'],
        'railway' => ['station'],
        'amenity' => ['hospital', 'university', 'college', 'ferry_terminal', 'bus_station'],
        'historic' => ['castle'],
    ];

    public const DEFAULT_LANGUAGES = ['sk', 'en', 'de', 'hu', 'cs', 'pl', 'hr', 'sl', 'it'];

    /**
     * @param array<string, int>          $placeTypes   OSM values of the key "place" that are places, with their base rank
     * @param array<string, list<string>> $pois         OSM key => its values that are points of interest
     * @param int                         $poiRank      the base rank of a point of interest
     * @param int                         $streetRank   the base rank of a street
     * @param list<string>                $languages    ISO 639-1: the translations kept in the documents and the catalog of names
     * @param float                       $placeDedupKm places of the same name and country closer than this are one place (the most important is kept)
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public array $placeTypes = self::DEFAULT_PLACE_TYPES,
        public array $pois = self::DEFAULT_POIS,
        public int $poiRank = 20,
        public int $streetRank = 25,
        public array $languages = self::DEFAULT_LANGUAGES,
        public float $placeDedupKm = 5.0,
    ) {
        foreach ($placeTypes as $type => $rank) {
            if (!\is_string($type) || '' === $type || !\is_int($rank) || $rank < 0) {
                throw new InvalidArgumentException('The place types must map OSM values to base ranks of zero or more.');
            }
        }
        foreach ($pois as $key => $values) {
            if (!\is_string($key) || '' === $key || !\is_array($values) || !array_is_list($values) || [] === $values
                || [] !== array_filter($values, static fn (mixed $value): bool => !\is_string($value) || '' === $value)) {
                throw new InvalidArgumentException('The points of interest must map OSM keys to non-empty lists of OSM values.');
            }
        }
        if ($poiRank < 0 || $streetRank < 0) {
            throw new InvalidArgumentException('The base ranks must not be negative.');
        }
        if ([] !== array_filter($languages, static fn (mixed $language): bool => !\is_string($language) || 1 !== preg_match('/^[a-z]{2,3}$/D', $language))) {
            throw new InvalidArgumentException('The languages must be a list of ISO 639 codes in lower case.');
        }
        if (!is_finite($placeDedupKm) || $placeDedupKm < 0.0) {
            throw new InvalidArgumentException('The distance of duplicate places must not be negative.');
        }
    }

    /**
     * The base rank of a place of the given OSM value, or null when such places are not selected.
     */
    public function placeRank(string $osmValue): ?int
    {
        return $this->placeTypes[$osmValue] ?? null;
    }

    public function isPoi(string $osmKey, string $osmValue): bool
    {
        return \in_array($osmValue, $this->pois[$osmKey] ?? [], true);
    }

    /**
     * The rank of a document of the given base rank and Nominatim importance.
     *
     * @param float $importance 0 to 1; out-of-range values are clamped
     */
    public function rank(int $baseRank, float $importance): int
    {
        return $baseRank * 1000 + (int) round(max(0.0, min(1.0, $importance)) * 999);
    }
}
