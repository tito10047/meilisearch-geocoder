<?php

namespace Tito10047\Geocoder\Name;

/**
 * What a translated name names.
 */
enum NameKind: string
{
    case Suburb = 'suburb';
    case City = 'city';
    case County = 'county';
    case Region = 'region';
    case Country = 'country';
}
