<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Import\SelectionRules;

/**
 * Selects by the SelectionRules: named places of the listed types, named points of interest of
 * the listed tags, and the named highways as streets, except those the rules exclude (bus stops,
 * platforms, junctions, …).
 */
final readonly class RulesPlaceSelector implements PlaceSelectorInterface
{
    private const STREET_KEY = 'highway';
    private const PLACE_KEY = 'place';

    public function __construct(
        private SelectionRules $rules,
    ) {
    }

    public function select(Place $place): ?DocumentKind
    {
        if (null === $place->name()) {
            return null;
        }
        // a highway that is not a road may still be a point of interest of the rules ("services")
        if (self::STREET_KEY === $place->osmKey && $this->rules->isStreet($place->osmValue)) {
            return DocumentKind::Street;
        }
        if (self::PLACE_KEY === $place->osmKey && null !== $this->rules->placeRank($place->osmValue)) {
            return DocumentKind::Place;
        }

        return $this->rules->isPoi($place->osmKey, $place->osmValue) ? DocumentKind::Poi : null;
    }
}
