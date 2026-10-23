<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Document\DocumentKind;

/**
 * Decides which objects of a dump become documents, and of which kind.
 */
interface PlaceSelectorInterface
{
    /**
     * The kind of document the object becomes, or null when it is left out.
     *
     * The house number of an address is kept whatever this returns; an address becomes a
     * document only when it is a point of interest.
     */
    public function select(Place $place): ?DocumentKind;
}
