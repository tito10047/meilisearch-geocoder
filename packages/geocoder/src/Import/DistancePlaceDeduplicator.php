<?php

namespace Tito10047\Geocoder\Import;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Keeps one place per name within a distance: the one of the highest rank, which takes the
 * postcode of a merged place when it has none (the boundary relation of a town has no postcode,
 * its label node has one).
 */
final readonly class DistancePlaceDeduplicator implements PlaceDeduplicatorInterface
{
    /** The length of a degree of latitude, in kilometres. */
    private const KM_PER_DEGREE = 111.2;

    /**
     * @param float $kilometres places closer than this are one place
     *
     * @throws InvalidArgumentException when the distance is negative
     */
    public function __construct(
        private float $kilometres,
    ) {
        if (!is_finite($kilometres) || $kilometres < 0.0) {
            throw new InvalidArgumentException('The distance of duplicate places must not be negative.');
        }
    }

    public function deduplicate(array $places): array
    {
        // usort is stable: places of the same rank stay in the order of the dump
        usort($places, static fn (Document $a, Document $b): int => $b->rank <=> $a->rank);

        $kept = [];
        foreach ($places as $place) {
            foreach ($kept as $index => $keptPlace) {
                if ($this->distance($keptPlace->coordinates, $place->coordinates) < $this->kilometres) {
                    if (null === $keptPlace->postcode && null !== $place->postcode) {
                        $kept[$index] = $this->withPostcode($keptPlace, $place->postcode);
                    }
                    continue 2;
                }
            }
            $kept[] = $place;
        }

        return array_values($kept);
    }

    /**
     * An equirectangular approximation, precise enough for a few kilometres.
     */
    private function distance(Coordinates $from, Coordinates $to): float
    {
        return self::KM_PER_DEGREE * sqrt(
            ($from->latitude - $to->latitude) ** 2
            + (($from->longitude - $to->longitude) * cos(deg2rad($from->latitude))) ** 2
        );
    }

    private function withPostcode(Document $document, string $postcode): Document
    {
        return new Document(
            $document->id,
            $document->kind,
            $document->type,
            $document->name,
            $document->names,
            $document->cities,
            $document->namesI18n,
            $document->city,
            $document->region,
            $document->countryCode,
            $postcode,
            $document->coordinates,
            $document->rank,
        );
    }
}
