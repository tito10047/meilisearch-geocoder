<?php

namespace Tito10047\GeoBundle;

/**
 * The attribution the OpenStreetMap data requires wherever its derivatives are shown.
 *
 * The addresses and the routes come from OpenStreetMap, available under the Open Database
 * License: whoever displays them must credit the contributors.
 *
 * @see https://www.openstreetmap.org/copyright
 */
final class Attribution
{
    public const OPENSTREETMAP = '© OpenStreetMap contributors, ODbL (https://www.openstreetmap.org/copyright)';

    private function __construct()
    {
    }
}
