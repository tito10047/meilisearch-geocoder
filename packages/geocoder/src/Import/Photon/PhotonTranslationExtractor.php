<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\Translation;

/**
 * Reads the translations of the parts of towns, the towns, the counties and the regions from the
 * address keys "suburb:xx", "city:xx", "county:xx" and "state:xx" (as far as the address
 * interpreter trusts them), those of towns and parts of towns also from their own records
 * ("place=town" with "name:xx"), and those of countries from the "CountryInfo" record.
 *
 * A country is named by its code, which no one reads: all its translations are kept, the one in
 * its own language included. Its English name and its own name (Translation::LOCAL_LANGUAGE) are
 * kept whatever the languages: a country typed into a query ("Košice, Slovakia", "Wien, Österreich")
 * is recognized by any of them.
 */
final readonly class PhotonTranslationExtractor implements TranslationExtractorInterface
{
    /** The "place" values of the records of towns: a town of an address is one of them. */
    private const TOWNS = ['city', 'town', 'village', 'municipality'];

    /** The "place" values of the records of parts of towns; a part of a town may be a village of its own. */
    private const SUBURBS = ['suburb', 'quarter', 'neighbourhood', 'borough', 'city_district', 'village', 'hamlet'];

    /** The language of the country names typed by anyone. */
    private const ENGLISH = 'en';

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
        if (!$this->hasCountry($place)) {
            return [];
        }

        $names = [
            [NameKind::City, $this->address->town($place)],
            [NameKind::Suburb, $this->address->suburb($place)],
            [NameKind::County, $this->address->county($place)],
            [NameKind::Region, $this->address->region($place)],
        ];
        $translations = [];
        foreach ($names as [$kind, $name]) {
            if ('' === $name) {
                continue;
            }
            foreach ($this->address->catalogTranslations($place, $kind) as $language => $translation) {
                $translations[] = new Translation($place->countryCode, $kind, $name, $language, $translation);
            }
        }

        return $translations;
    }

    public function fromOwnNames(Place $place): array
    {
        $name = $place->name();
        if (null === $name || 'place' !== $place->osmKey || !$this->hasCountry($place)) {
            return [];
        }

        $translations = [];
        foreach ([[NameKind::City, self::TOWNS], [NameKind::Suburb, self::SUBURBS]] as [$kind, $values]) {
            if (!\in_array($place->osmValue, $values, true)) {
                continue;
            }
            foreach ($this->languages as $language) {
                $translation = $place->names['name:'.$language] ?? '';
                // no name in a language: the town is called by its local name in it
                $translations[] = new Translation($place->countryCode, $kind, $name, $language, '' === $translation ? $name : $translation);
            }
        }

        return $translations;
    }

    public function fromCountry(Country $country): array
    {
        $tags = [];
        foreach ([...$this->languages, self::ENGLISH] as $language) {
            $tags['name:'.$language] = $language;
        }
        $tags['name'] = Translation::LOCAL_LANGUAGE;

        $translations = [];
        foreach ($tags as $tag => $language) {
            $translation = trim($country->names[$tag] ?? '');
            if ('' !== $translation) {
                $translations[] = new Translation($country->countryCode, NameKind::Country, $country->countryCode, $language, $translation);
            }
        }

        return $translations;
    }

    private function hasCountry(Place $place): bool
    {
        return 1 === preg_match('/^[A-Z]{2}$/D', $place->countryCode);
    }
}
