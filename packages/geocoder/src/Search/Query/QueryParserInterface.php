<?php

namespace Tito10047\Geocoder\Search\Query;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Splits the text typed by the user into the part searched by name and the house number.
 */
interface QueryParserInterface
{
    /**
     * @return ParsedQuery|null null when there is nothing to search (an empty or blank text)
     *
     * @throws InvalidArgumentException when the text is not valid UTF-8
     */
    public function parse(string $text): ?ParsedQuery;
}
