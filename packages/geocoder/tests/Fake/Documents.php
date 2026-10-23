<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;

/**
 * Builds the documents of the tests.
 */
final class Documents
{
    /**
     * @param array<string, string> $namesI18n
     */
    public static function create(
        string $id,
        string $name,
        string $countryCode = 'SK',
        DocumentKind $kind = DocumentKind::Place,
        string $type = 'village',
        int $rank = 50000,
        string $city = '',
        string $region = '',
        array $namesI18n = [],
        ?string $postcode = null,
    ): Document {
        return new Document($id, $kind, $type, $name, [$name], [], $namesI18n, $city, $region, $countryCode, $postcode, new Coordinates(48.0, 17.0), $rank);
    }
}
