<?php

namespace Tito10047\Geocoder\Index;

/**
 * How precisely Meilisearch measures the distance between the words of a query (setting "proximityPrecision").
 */
enum ProximityPrecision: string
{
    /** The distance in words: precise, but a bigger index and a slower indexing. */
    case ByWord = 'byWord';

    /** Only whether the words are in the same attribute: enough for short names, a much smaller index. */
    case ByAttribute = 'byAttribute';
}
