<?php

namespace Tito10047\Geocoder\Import\Workspace;

/**
 * Where a candidate translation of a name was read.
 */
enum TranslationSource: string
{
    /** An address of an object in the named town (or part of a town, county, region). */
    case Address = 'address';
    /** The record of the named town (or part of a town) itself. */
    case OwnName = 'own_name';
}
