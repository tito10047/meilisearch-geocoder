<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Name\CountryNameSourceInterface;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;

/**
 * Lists the names given ("SK" => ["sk" => "Slovensko"]) and counts how often it was asked.
 */
final class FakeCountryNameSource implements CountryNameSourceInterface
{
    public int $reads = 0;

    /**
     * @param array<string, array<string, string>> $names the names by country code and language
     */
    public function __construct(
        private readonly array $names,
    ) {
    }

    public function countryNames(): array
    {
        ++$this->reads;
        $translations = [];
        foreach ($this->names as $countryCode => $names) {
            foreach ($names as $language => $name) {
                $translations[] = new Translation($countryCode, NameKind::Country, $countryCode, $language, $name);
            }
        }

        return $translations;
    }
}
