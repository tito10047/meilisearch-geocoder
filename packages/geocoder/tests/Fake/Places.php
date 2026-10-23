<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Import\Photon\Place;

/**
 * Builds places of a dump for the tests, with the values of a Slovak village by default.
 */
final class Places
{
    /**
     * @param array<string, string>|null $names
     * @param array<string, string>|null $address
     */
    public static function create(
        ?string $objectType = 'N',
        ?int $objectId = 1,
        string $osmKey = 'place',
        string $osmValue = 'village',
        float $importance = 0.25,
        ?array $names = null,
        ?string $houseNumber = null,
        ?array $address = null,
        ?string $postcode = '076 31',
        string $countryCode = 'SK',
        float $latitude = 48.3996662,
        float $longitude = 21.747595,
    ): Place {
        return new Place(
            $objectType,
            $objectId,
            $osmKey,
            $osmValue,
            $importance,
            $names ?? ['name' => 'Viničky', 'name:hu' => 'Szőlőske'],
            $houseNumber,
            $address ?? ['city' => 'Viničky', 'city:hu' => 'Szőlőske', 'city:sk' => 'Viničky', 'state' => 'Košický kraj', 'state:hu' => 'Kassai kerület', 'state:en' => 'Region of Košice'],
            $postcode,
            $countryCode,
            new Coordinates($latitude, $longitude),
        );
    }
}
