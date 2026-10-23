<?php

namespace Tito10047\Geocoder\Name;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * The name of a town, a region or a country in one language: "Wien" is "Viedeň" in Slovak.
 */
final readonly class Translation
{
    /**
     * ISO 639-2 "undetermined": the language of a country's own name ("Schweiz/Suisse/Svizzera/Svizra"),
     * which its OSM "name" does not tell.
     */
    public const LOCAL_LANGUAGE = 'und';

    /**
     * @param string $countryCode ISO 3166-1 alpha-2, upper case: the country the named thing is in
     * @param string $name        the local name, as in the documents
     * @param string $language    ISO 639-1, lower case
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public string $countryCode,
        public NameKind $kind,
        public string $name,
        public string $language,
        public string $translation,
    ) {
        if (1 !== preg_match('/^[A-Z]{2}$/D', $countryCode)) {
            throw new InvalidArgumentException(\sprintf('The country code must be two upper case letters, "%s" given.', $countryCode));
        }
        if (1 !== preg_match('/^[a-z]{2,3}$/D', $language)) {
            throw new InvalidArgumentException(\sprintf('The language must be an ISO 639 code in lower case, "%s" given.', $language));
        }
        if ('' === $name || '' === $translation) {
            throw new InvalidArgumentException('A name and its translation must not be empty.');
        }
    }
}
