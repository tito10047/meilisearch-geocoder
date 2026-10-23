<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\HouseNumber\HouseNumber;
use Tito10047\Geocoder\HouseNumber\HouseNumberReferenceStoreInterface;
use Tito10047\Geocoder\Name\NameCatalogInterface;
use Tito10047\Geocoder\Search\Backend\DocumentSourceInterface;

/**
 * Finds the house number with the reference in the database, then its street document in the
 * search engine: the address is the one suggest() returns for that house, labelled the same way.
 *
 * A house whose street has no document (its address names neither a street nor a place) is not
 * found, as suggest() cannot find it either.
 */
final readonly class AddressLookup implements AddressLookupInterface
{
    private SuggestionFactory $suggestions;

    public function __construct(
        private HouseNumberReferenceStoreInterface $houseNumbers,
        private DocumentSourceInterface $documents,
        NameCatalogInterface $catalog,
        LabelFormatterInterface $labelFormatter,
    ) {
        $this->suggestions = new SuggestionFactory($catalog, $labelFormatter);
    }

    public function findByReference(string $reference, ?SearchOptions $options = null): ?Suggestion
    {
        $reference = trim($reference);
        if ('' === $reference) {
            return null;
        }

        $houseNumber = $this->houseNumbers->findByReference($reference);
        if (!$houseNumber instanceof HouseNumber) {
            return null;
        }
        $street = $this->documents->document($houseNumber->streetKey->documentId());
        if (!$street instanceof Document) {
            return null;
        }

        $language = ($options ?? new SearchOptions())->language();

        return $this->suggestions->address($street, $this->suggestions->fromDocument($street, $language), $houseNumber, $language);
    }
}
