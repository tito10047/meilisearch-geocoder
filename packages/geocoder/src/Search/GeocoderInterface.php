<?php

namespace Tito10047\Geocoder\Search;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Finds places, streets, addresses and points of interest by their names.
 */
interface GeocoderInterface
{
    /**
     * Suggestions while the user types: a possibly unfinished address or place name.
     *
     * @return list<Suggestion> the best match first; [] when nothing matches or the text is too short
     *
     * @throws InvalidArgumentException     when the text is not valid UTF-8
     * @throws GeocoderUnavailableException when Meilisearch or the database cannot answer
     */
    public function suggest(string $text, ?SearchOptions $options = null): array;

    /**
     * The best match of a complete address or place name.
     *
     * @return Suggestion|null null when nothing matches
     *
     * @throws InvalidArgumentException     when the text is not valid UTF-8
     * @throws GeocoderUnavailableException when Meilisearch or the database cannot answer
     */
    public function geocode(string $text, ?SearchOptions $options = null): ?Suggestion;
}
