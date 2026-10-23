<?php

namespace Tito10047\Geocoder\Photon;

use Tito10047\Geocoder\Search\Kind;

/**
 * The layers of Photon ("type" of a feature, "layer" filter): the level of an object in the
 * address hierarchy, from a house up to a country.
 *
 * @see https://github.com/komoot/photon/blob/master/docs/api-v1.md#filter-results-by-layer
 */
enum Layer: string
{
    case House = 'house';
    case Street = 'street';
    case Locality = 'locality';
    case District = 'district';
    case City = 'city';
    case County = 'county';
    case State = 'state';
    case Country = 'country';
    case Other = 'other';

    /**
     * The kinds of suggestions a feature of this layer can be: the index holds no counties,
     * states, countries nor natural features, so their layers hold nothing.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return match ($this) {
            self::House => [Kind::Address, Kind::Poi],
            self::Street => [Kind::Street],
            self::Locality, self::District, self::City => [Kind::Place],
            self::County, self::State, self::Country, self::Other => [],
        };
    }

    /**
     * "house, street, …" for the error messages.
     */
    public static function names(): string
    {
        return implode(', ', array_map(static fn (self $layer): string => $layer->value, self::cases()));
    }
}
