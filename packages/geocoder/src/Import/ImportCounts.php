<?php

namespace Tito10047\Geocoder\Import;

/**
 * How many things an import has written so far.
 */
final readonly class ImportCounts
{
    public function __construct(
        public int $places = 0,
        public int $streets = 0,
        public int $pois = 0,
        public int $houseNumbers = 0,
    ) {
    }

    public function add(self $other): self
    {
        return new self(
            $this->places + $other->places,
            $this->streets + $other->streets,
            $this->pois + $other->pois,
            $this->houseNumbers + $other->houseNumbers,
        );
    }

    /**
     * The number of documents: places, streets and points of interest.
     */
    public function documents(): int
    {
        return $this->places + $this->streets + $this->pois;
    }
}
