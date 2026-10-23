<?php

namespace Tito10047\Geocoder\Search;

/**
 * What a suggestion is.
 */
enum Kind: string
{
    /** A town, a village or a part of one. */
    case Place = 'place';

    /** A street within its town. */
    case Street = 'street';

    /** A house number of a street. */
    case Address = 'address';

    /** A point of interest a trip can head for: a hotel, a station, an airport, … */
    case Poi = 'poi';
}
