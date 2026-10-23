<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;

/**
 * Builds the documents of the import tests: a village with a translated town by default.
 */
final class ImportDocuments
{
    /**
     * @param list<string> $names
     */
    public static function create(
        string $id = 'N1',
        DocumentKind $kind = DocumentKind::Place,
        string $type = 'village',
        string $name = 'Viničky',
        array $names = ['Viničky'],
        ?string $postcode = null,
        float $latitude = 48.4,
        float $longitude = 21.75,
        int $rank = 50000,
        string $countryCode = 'SK',
    ): Document {
        return new Document($id, $kind, $type, $name, $names, ['Viničky', 'Szőlőske'], ['hu' => 'Szőlőske'], 'Viničky', 'Košický kraj', $countryCode, $postcode, new Coordinates($latitude, $longitude), $rank);
    }
}
