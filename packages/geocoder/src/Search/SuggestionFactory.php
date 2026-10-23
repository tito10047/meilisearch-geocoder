<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\Name\NameCatalogInterface;
use Tito10047\Geocoder\Name\NameKind;

/**
 * Turns the documents and the house numbers into labelled suggestions, named in the requested
 * language: the translations of the document, the parts of the address from the name catalog.
 *
 * @internal shared by the Geocoder and the AddressLookup
 */
final readonly class SuggestionFactory
{
    public const ADDRESS_TYPE = 'house';

    public function __construct(
        private NameCatalogInterface $catalog,
        private LabelFormatterInterface $labelFormatter,
    ) {
    }

    public function fromDocument(Document $document, ?string $language): Suggestion
    {
        return $this->labelled(new Suggestion(
            id: $document->id,
            kind: match ($document->kind) {
                DocumentKind::Place => Kind::Place,
                DocumentKind::Street => Kind::Street,
                DocumentKind::Poi => Kind::Poi,
            },
            type: $document->type,
            name: null === $language ? $document->name : ($document->namesI18n[$language] ?? $document->name),
            label: '',
            coordinates: $document->coordinates,
            countryCode: $document->countryCode,
            city: $this->translate($document->countryCode, NameKind::City, $document->city, $language),
            region: $this->translate($document->countryCode, NameKind::Region, $document->region, $language),
            postcode: $document->postcode,
            suburb: $this->translate($document->countryCode, NameKind::Suburb, $document->suburb, $language),
            county: $this->translate($document->countryCode, NameKind::County, $document->county, $language),
        ), $language);
    }

    /**
     * The address of a house number of the street; the house's own part of the town wins over
     * the street's (a long street may cross several).
     *
     * @param Suggestion $street the suggestion of the street document, in the same language
     */
    public function address(Document $streetDocument, Suggestion $street, HouseNumber $houseNumber, ?string $language): Suggestion
    {
        return $this->labelled(new Suggestion(
            id: $streetDocument->id.'#'.$houseNumber->number,
            kind: Kind::Address,
            type: self::ADDRESS_TYPE,
            name: $street->name.' '.$houseNumber->number,
            label: '',
            coordinates: $houseNumber->coordinates,
            countryCode: $streetDocument->countryCode,
            city: $street->city,
            region: $street->region,
            postcode: $houseNumber->postcode ?? $streetDocument->postcode,
            street: $street->name,
            houseNumber: $houseNumber->number,
            suburb: null === $houseNumber->suburb ? $street->suburb : $this->translate($streetDocument->countryCode, NameKind::Suburb, $houseNumber->suburb, $language),
            county: $street->county,
            reference: $houseNumber->reference,
        ), $language);
    }

    private function labelled(Suggestion $suggestion, ?string $language): Suggestion
    {
        return $suggestion->withLabel($this->labelFormatter->format($suggestion, $language));
    }

    private function translate(string $countryCode, NameKind $kind, string $name, ?string $language): ?string
    {
        if ('' === $name) {
            return null;
        }
        if (null === $language) {
            return $name;
        }

        return $this->catalog->translate($countryCode, $kind, $name, $language) ?? $name;
    }
}
