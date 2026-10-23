<?php

namespace Tito10047\Geocoder\Photon;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * One result of the Photon API: a GeoJSON feature with a point geometry and the properties of
 * Photon (GeocodeJSON); an unknown property is left out, as Photon does.
 *
 * @see https://github.com/komoot/photon/blob/master/docs/api-v1.md#results-for-search-and-reverse
 */
final readonly class Feature implements \JsonSerializable
{
    /**
     * @param Layer       $layer       the "type" property: the level in the address hierarchy
     * @param string|null $osmType     "N", "W" or "R"; null when the feature is no single OSM object (a street merged from ways, an address)
     * @param int|null    $osmId       the id of the OSM object
     * @param string|null $osmKey      the main OSM tag: "place", "highway", "tourism", …
     * @param string|null $osmValue    its value: "city", "hotel", …
     * @param string|null $district    the part of the town
     * @param string|null $county      the county (district of a region)
     * @param string|null $state       the region
     * @param string|null $country     the name of the country
     * @param string      $countryCode ISO 3166-1 alpha-2, upper case
     *
     * @throws InvalidArgumentException when the OSM type or the country code is invalid
     */
    public function __construct(
        public Coordinates $coordinates,
        public Layer $layer,
        public string $countryCode,
        public ?string $osmType = null,
        public ?int $osmId = null,
        public ?string $osmKey = null,
        public ?string $osmValue = null,
        public ?string $name = null,
        public ?string $houseNumber = null,
        public ?string $street = null,
        public ?string $postcode = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $country = null,
        public ?string $district = null,
        public ?string $county = null,
    ) {
        if (null !== $osmType && !\in_array($osmType, ['N', 'W', 'R'], true)) {
            throw new InvalidArgumentException(\sprintf('The OSM type must be "N", "W" or "R", "%s" given.', $osmType));
        }
        if (1 !== preg_match('/^[A-Z]{2}$/D', $countryCode)) {
            throw new InvalidArgumentException(\sprintf('The country code must be two upper case letters, "%s" given.', $countryCode));
        }
    }

    /**
     * The categories of Photon ("include"/"exclude" filters): only the reserved group "osm" with the main tag.
     *
     * @return list<list<string>> the labels of each category: [["osm", "tourism", "hotel"]]
     */
    public function categories(): array
    {
        if (null === $this->osmKey) {
            return [];
        }

        return [null === $this->osmValue ? ['osm', $this->osmKey] : ['osm', $this->osmKey, $this->osmValue]];
    }

    /**
     * The properties in the order of Photon, without the unknown ones.
     *
     * @return array{type: 'Feature', properties: array<string, string|int>, geometry: array{type: 'Point', coordinates: array{float, float}}}
     */
    public function jsonSerialize(): array
    {
        $properties = array_filter([
            'osm_type' => $this->osmType,
            'osm_id' => $this->osmId,
            'osm_key' => $this->osmKey,
            'osm_value' => $this->osmValue,
            'type' => $this->layer->value,
            'housenumber' => $this->houseNumber,
            'name' => $this->name,
            'street' => $this->street,
            'district' => $this->district,
            'city' => $this->city,
            'county' => $this->county,
            'state' => $this->state,
            'country' => $this->country,
            'postcode' => $this->postcode,
            'countrycode' => $this->countryCode,
        ], static fn (string|int|null $value): bool => null !== $value && '' !== $value);

        return [
            'type' => 'Feature',
            'properties' => $properties,
            'geometry' => ['type' => 'Point', 'coordinates' => [$this->coordinates->longitude, $this->coordinates->latitude]],
        ];
    }
}
