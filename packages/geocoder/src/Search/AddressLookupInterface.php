<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;

/**
 * Finds an address by the official id the data gives it (Suggestion::$reference): in Slovakia
 * the id of the register of addresses ("ref:minvskaddress", its "buildingIndex").
 */
interface AddressLookupInterface
{
    /**
     * The address with the reference, compared exactly.
     *
     * @param SearchOptions|null $options only the locale is used: the language of the names and the label
     *
     * @return Suggestion|null a Kind::Address suggestion without a QueryMatch (nothing was searched by name); null when
     *                         no address has the reference
     *
     * @throws GeocoderUnavailableException when Meilisearch or the database cannot answer
     */
    public function findByReference(string $reference, ?SearchOptions $options = null): ?Suggestion;
}
