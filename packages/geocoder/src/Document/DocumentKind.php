<?php

namespace Tito10047\Geocoder\Document;

/**
 * What a document of the index is: the "kind" field, a filter.
 */
enum DocumentKind: string
{
    /** A town, a village or a part of one (OSM key "place"). */
    case Place = 'place';

    /** A street within its town, merged from all its OSM ways and the addresses that name it. */
    case Street = 'street';

    /** A point of interest a trip can head for: a hotel, a station, an airport, a castle, … */
    case Poi = 'poi';
}
