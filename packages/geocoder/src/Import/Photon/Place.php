<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Coordinates;

/**
 * One object of a "Place" record of a dump: a town, a street, an address, a point of interest, ….
 *
 * Only the fields the geocoder uses are read; the geometry is left out.
 */
final readonly class Place
{
    /**
     * @param string|null           $objectType  the OSM type: "N" (node), "W" (way) or "R" (relation); null for an object
     *                                           Nominatim made up (a postcode area)
     * @param int|null              $objectId    the OSM id, null with the type
     * @param string                $osmKey      the main OSM tag: "place", "highway", "tourism", …
     * @param string                $osmValue    its value: "city", "residential", "hotel", …
     * @param float                 $importance  Nominatim's importance, 0 to 1
     * @param array<string, string> $names       the OSM name tags: "name" => "Wien", "name:sk" => "Viedeň", …
     * @param string|null           $houseNumber the house number of an address: "12", "606/42"
     * @param array<string, string> $address     the address: "street", "city", "town", "state", "city:hu", …
     * @param string                $countryCode ISO 3166-1 alpha-2, upper case ("" when the dump has none)
     * @param Coordinates           $centroid    the centre of the object
     * @param array<string, string> $extra       the other OSM tags Photon exports ("extra"): "ref:minvskaddress" => "7101722639", …
     */
    public function __construct(
        public ?string $objectType,
        public ?int $objectId,
        public string $osmKey,
        public string $osmValue,
        public float $importance,
        public array $names,
        public ?string $houseNumber,
        public array $address,
        public ?string $postcode,
        public string $countryCode,
        public Coordinates $centroid,
        public array $extra = [],
    ) {
    }

    /**
     * The OSM type and id, "N1047534408", or null for an object Nominatim made up.
     */
    public function osmId(): ?string
    {
        return null === $this->objectType || null === $this->objectId ? null : $this->objectType.$this->objectId;
    }

    /**
     * The local name ("name" tag), or null when the object has none.
     */
    public function name(): ?string
    {
        $name = $this->names['name'] ?? null;

        return '' === $name ? null : $name;
    }

    /**
     * The value of an "extra" tag, trimmed; null when the object has none or an empty one.
     */
    public function extraTag(string $key): ?string
    {
        $value = trim($this->extra[$key] ?? '');

        return '' === $value ? null : $value;
    }
}
