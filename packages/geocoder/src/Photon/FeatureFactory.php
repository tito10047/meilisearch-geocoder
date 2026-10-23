<?php

namespace Tito10047\Geocoder\Photon;

use Tito10047\Geocoder\Import\SelectionRules;
use Tito10047\Geocoder\Name\NameCatalogInterface;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Search\Kind;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * The features of Photon from the suggestions, as close to Photon as the index allows.
 *
 * - "osm_type"/"osm_id" for the OSM objects (places, points of interest); a street merged from
 *   its ways and an address (a house number of a street) have none.
 * - "osm_key"/"osm_value": "place" and the type for a place, "place"/"house" for an address
 *   (Nominatim's tag of an address point), the key of the selection rules listing the type for a
 *   point of interest, "highway" without a value for a street (the value of its ways is lost when
 *   they are merged).
 * - "type" (the layer): "house" for an address and a point of interest, "street", and for a place
 *   the layer Photon gives its type (see PLACE_LAYERS; an unknown type is a "locality").
 * - "name" except for an address (Photon names only named houses), "housenumber", "street",
 *   "postcode", "district" (the suburb, not for a district itself), "city" (not for a town
 *   itself), "county", "state" (the region), "country" (named in the language when the catalog
 *   translates it; the local name of a country is not stored) and "countrycode". "locality" and
 *   "extent" are not known.
 */
final readonly class FeatureFactory implements FeatureFactoryInterface
{
    /**
     * The layers of the OSM values of places, as Photon 1.3 classifies them (by the address rank of Nominatim).
     */
    public const PLACE_LAYERS = [
        'city' => Layer::City,
        'town' => Layer::City,
        'village' => Layer::District,
        'suburb' => Layer::District,
        'borough' => Layer::District,
        'hamlet' => Layer::District,
        'quarter' => Layer::Locality,
        'neighbourhood' => Layer::Locality,
    ];

    private const PLACE_KEY = 'place';
    private const STREET_KEY = 'highway';
    private const ADDRESS_VALUE = 'house';

    /** @var array<string, string> the OSM key of each OSM value of a point of interest (the first key listing it) */
    private array $poiKeys;

    public function __construct(
        private NameCatalogInterface $catalog,
        SelectionRules $rules = new SelectionRules(),
    ) {
        $poiKeys = [];
        foreach ($rules->pois as $key => $values) {
            foreach ($values as $value) {
                $poiKeys[$value] ??= $key;
            }
        }
        $this->poiKeys = $poiKeys;
    }

    public function create(Suggestion $suggestion, ?string $language): Feature
    {
        [$osmType, $osmId] = 1 === preg_match('/^([NWR])(\d{1,18})$/D', $suggestion->id, $match) ? [$match[1], (int) $match[2]] : [null, null];
        [$layer, $osmKey, $osmValue] = match ($suggestion->kind) {
            Kind::Place => [self::PLACE_LAYERS[$suggestion->type] ?? Layer::Locality, self::PLACE_KEY, $suggestion->type],
            Kind::Street => [Layer::Street, self::STREET_KEY, null],
            Kind::Address => [Layer::House, self::PLACE_KEY, self::ADDRESS_VALUE],
            Kind::Poi => [Layer::House, $this->poiKeys[$suggestion->type] ?? null, $suggestion->type],
        };

        return new Feature(
            coordinates: $suggestion->coordinates,
            layer: $layer,
            countryCode: $suggestion->countryCode,
            osmType: $osmType,
            osmId: $osmId,
            osmKey: $osmKey,
            // a value without its key cannot be told apart from another key's: both or none
            osmValue: null === $osmKey ? null : $osmValue,
            name: Kind::Address === $suggestion->kind ? null : $suggestion->name,
            houseNumber: $suggestion->houseNumber,
            street: $suggestion->street,
            postcode: $suggestion->postcode,
            // Photon gives a town no town of its own
            city: Layer::City === $layer ? null : $suggestion->city,
            state: $suggestion->region,
            country: null === $language ? null : $this->catalog->translate($suggestion->countryCode, NameKind::Country, $suggestion->countryCode, $language),
            // nor a district one
            district: Layer::District === $layer ? null : $suggestion->suburb,
            county: $suggestion->county,
        );
    }
}
