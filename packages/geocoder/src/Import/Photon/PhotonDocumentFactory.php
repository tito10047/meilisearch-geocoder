<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\Import\SelectionRules;
use Tito10047\Geocoder\StreetKey;

/**
 * Builds the documents of the prototype's variant D: every name and every name of the town
 * searchable ("names", "cities"), the translations of the name displayed ("names_i18n").
 */
final readonly class PhotonDocumentFactory implements DocumentFactoryInterface
{
    private const STREET_TYPE = 'street';

    private AddressInterpreterInterface $address;

    public function __construct(
        private SelectionRules $rules,
        ?AddressInterpreterInterface $address = null,
    ) {
        $this->address = $address ?? new PhotonAddressInterpreter($rules->languages);
    }

    public function place(Place $place): ?Document
    {
        $baseRank = $this->rules->placeRank($place->osmValue);
        if (null === $baseRank) {
            return null;
        }

        return $this->named($place, DocumentKind::Place, $this->address->townOfPlace($place), $baseRank);
    }

    public function poi(Place $place): ?Document
    {
        return $this->named($place, DocumentKind::Poi, $this->address->town($place), $this->rules->poiRank);
    }

    public function street(Place $place): ?Document
    {
        $name = $place->name();
        if (null === $name || !$this->hasCountry($place)) {
            return null;
        }
        $town = $this->address->town($place);

        return new Document(
            id: StreetKey::fromAddress($place->countryCode, $town, $name)->documentId(),
            kind: DocumentKind::Street,
            type: self::STREET_TYPE,
            name: $name,
            names: $this->names($place, $name),
            cities: $this->cities($place, $town),
            namesI18n: $this->nameTranslations($place, $name),
            city: $town,
            region: $this->address->region($place),
            countryCode: $place->countryCode,
            postcode: null,
            coordinates: $place->centroid,
            rank: $this->rules->rank($this->rules->streetRank, 0.0),
            suburb: $this->address->suburb($place),
            county: $this->address->county($place),
        );
    }

    public function streetOfAddress(Place $place): ?Document
    {
        $street = $this->address->street($place);
        if ('' === $street || !$this->hasCountry($place)) {
            return null;
        }
        $town = $this->address->town($place);

        return new Document(
            id: StreetKey::fromAddress($place->countryCode, $town, $street)->documentId(),
            kind: DocumentKind::Street,
            type: self::STREET_TYPE,
            name: $street,
            names: [$street],
            cities: $this->cities($place, $town),
            namesI18n: [],
            city: $town,
            region: $this->address->region($place),
            countryCode: $place->countryCode,
            postcode: null,
            coordinates: $place->centroid,
            rank: $this->rules->rank($this->rules->streetRank, 0.0),
            suburb: $this->address->suburb($place),
            county: $this->address->county($place),
        );
    }

    public function houseNumber(Place $place): ?HouseNumber
    {
        if (null === $place->houseNumber || '' === trim($place->houseNumber)) {
            return null;
        }

        $suburb = $this->address->suburb($place);

        return new HouseNumber(
            StreetKey::fromAddress($place->countryCode, $this->address->town($place), $this->address->street($place)),
            $place->houseNumber,
            $place->postcode,
            $place->centroid,
            '' === trim($suburb) ? null : $suburb,
            $this->reference($place),
        );
    }

    /**
     * The official id of the address, from the "extra" tag of its country; one the tables cannot
     * hold (longer than HouseNumber::MAX_REFERENCE_LENGTH) is left out rather than cut.
     */
    private function reference(Place $place): ?string
    {
        $key = $this->rules->referenceKey($place->countryCode);
        $reference = null === $key ? null : $place->extraTag($key);

        return null !== $reference && HouseNumber::isValidReference($reference) ? $reference : null;
    }

    private function named(Place $place, DocumentKind $kind, string $town, int $baseRank): ?Document
    {
        $name = $place->name();
        $id = $place->osmId();
        if (null === $name || null === $id || !$this->hasCountry($place)) {
            return null;
        }

        return new Document(
            id: $id,
            kind: $kind,
            type: $place->osmValue,
            name: $name,
            names: $this->names($place, $name),
            cities: $this->cities($place, $town),
            namesI18n: $this->nameTranslations($place, $name),
            city: $town,
            region: $this->address->region($place),
            countryCode: $place->countryCode,
            postcode: $place->postcode,
            coordinates: $place->centroid,
            rank: $this->rules->rank($baseRank, $place->importance),
            suburb: $this->address->suburb($place),
            county: $this->address->county($place),
        );
    }

    private function hasCountry(Place $place): bool
    {
        return 1 === preg_match('/^[A-Z]{2}$/D', $place->countryCode);
    }

    /**
     * The local name and its distinct translations, all searchable alike: a translation weighs
     * as much as the local name.
     *
     * @return list<string>
     */
    private function names(Place $place, string $name): array
    {
        return array_values(array_unique([$name, ...array_values($this->nameTranslations($place, $name))]));
    }

    /**
     * @return array<string, string>
     */
    private function nameTranslations(Place $place, string $name): array
    {
        $translations = [];
        foreach ($this->rules->languages as $language) {
            $translation = $place->names['name:'.$language] ?? null;
            if (null !== $translation && '' !== $translation && $translation !== $name) {
                $translations[$language] = $translation;
            }
        }

        return $translations;
    }

    /**
     * The town and its distinct translations, so that a street is found with its town in any language.
     *
     * @return list<string>
     */
    private function cities(Place $place, string $town): array
    {
        if ('' === $town) {
            return [];
        }

        return array_values(array_unique([$town, ...array_values($this->address->townTranslations($place, $town))]));
    }
}
