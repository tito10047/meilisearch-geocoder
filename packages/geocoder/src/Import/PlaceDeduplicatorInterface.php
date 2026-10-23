<?php

namespace Tito10047\Geocoder\Import;

use Tito10047\Geocoder\Document\Document;

/**
 * Merges the places that are one place to a user: a town comes both as its boundary relation
 * and as its label node, sometimes of another type and a few kilometres apart.
 */
interface PlaceDeduplicatorInterface
{
    /**
     * The places to keep of a group of places of the same name and country.
     *
     * @param list<Document> $places in the order of the dump
     *
     * @return list<Document>
     */
    public function deduplicate(array $places): array;
}
