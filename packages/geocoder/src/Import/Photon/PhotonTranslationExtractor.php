<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;

/**
 * Reads the translations of the parts of towns, the towns, the counties and the regions from the
 * address keys "suburb:xx", "city:xx", "county:xx" and "state:xx", and those of countries from the
 * "CountryInfo" record.
 *
 * A town, a county or a region is named in the documents by its local name, so only the translations that
 * differ from it are kept. A country is named by its code, which no one reads: all its
 * translations are kept, the one in its own language included.
 */
final readonly class PhotonTranslationExtractor implements TranslationExtractorInterface
{
    private AddressInterpreterInterface $address;

    /**
     * @param list<string> $languages ISO 639-1: the languages kept
     *
     * @throws InvalidArgumentException when a language is not an ISO 639 code in lower case
     */
    public function __construct(
        private array $languages,
        ?AddressInterpreterInterface $address = null,
    ) {
        $this->address = $address ?? new PhotonAddressInterpreter($languages);
    }

    public function fromPlace(Place $place): array
    {
        if (1 !== preg_match('/^[A-Z]{2}$/D', $place->countryCode)) {
            return [];
        }

        $translations = [];
        $town = $this->address->town($place);
        foreach ($this->address->townTranslations($place, $town) as $language => $translation) {
            $translations[] = new Translation($place->countryCode, NameKind::City, $town, $language, $translation);
        }
        $suburb = $this->address->suburb($place);
        foreach ($this->address->suburbTranslations($place) as $language => $translation) {
            $translations[] = new Translation($place->countryCode, NameKind::Suburb, $suburb, $language, $translation);
        }
        $county = $this->address->county($place);
        foreach ($this->address->countyTranslations($place) as $language => $translation) {
            $translations[] = new Translation($place->countryCode, NameKind::County, $county, $language, $translation);
        }
        $region = $this->address->region($place);
        foreach ($this->address->regionTranslations($place) as $language => $translation) {
            $translations[] = new Translation($place->countryCode, NameKind::Region, $region, $language, $translation);
        }

        return $translations;
    }

    public function fromCountry(Country $country): array
    {
        $translations = [];
        foreach ($this->languages as $language) {
            $translation = $country->names['name:'.$language] ?? null;
            if (null !== $translation && '' !== $translation) {
                $translations[] = new Translation($country->countryCode, NameKind::Country, $country->countryCode, $language, $translation);
            }
        }

        return $translations;
    }
}
