<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Coordinates;

/**
 * One suggestion of the geocoder: a place, a street, an address or a point of interest.
 */
final readonly class Suggestion
{
    /**
     * @param string      $id          "R109166" (OSM object), "s<street key>" (street), "s<street key>#12" (address)
     * @param string      $type        the OSM value: "city", "village", "hotel", "station", "street", "house"
     * @param string      $name        in the requested language when known: "Viedeň"
     * @param string      $label       the whole line, in the requested language when known: "Stephansplatz, Viedeň, Rakúsko"
     * @param string      $countryCode ISO 3166-1 alpha-2, upper case
     * @param string|null $city        the town, in the requested language when known
     * @param string|null $region      the region, in the requested language when known
     * @param string|null $street      the street of an address (Kind::Address)
     * @param string|null $houseNumber the house number of an address (Kind::Address)
     */
    public function __construct(
        public string $id,
        public Kind $kind,
        public string $type,
        public string $name,
        public string $label,
        public Coordinates $coordinates,
        public string $countryCode,
        public ?string $city = null,
        public ?string $region = null,
        public ?string $postcode = null,
        public ?string $street = null,
        public ?string $houseNumber = null,
    ) {
    }

    public function withLabel(string $label): self
    {
        return new self($this->id, $this->kind, $this->type, $this->name, $label, $this->coordinates, $this->countryCode,
            $this->city, $this->region, $this->postcode, $this->street, $this->houseNumber);
    }
}
