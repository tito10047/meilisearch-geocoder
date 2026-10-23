<?php

namespace Tito10047\Geocoder\Storage;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * The names of the database tables of the geocoder.
 *
 * An import writes into tables with a suffix (withSuffix()) and publishing renames them to the
 * live names, so that searches never see a half-written table. The names are interpolated into
 * SQL: they are validated as plain identifiers.
 */
final readonly class TableNames
{
    /**
     * @param string $houseNumbers the house numbers of the streets
     * @param string $translations the translated names of towns, regions and countries
     * @param string $metadata     key/value metadata: the schema version, the data timestamp of each country, …
     *
     * @throws InvalidArgumentException when a name is not a plain SQL identifier
     */
    public function __construct(
        public string $houseNumbers = 'geo_house',
        public string $translations = 'geo_name',
        public string $metadata = 'geo_meta',
    ) {
        foreach ([$houseNumbers, $translations, $metadata] as $name) {
            if (1 !== preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/D', $name)) {
                throw new InvalidArgumentException(\sprintf('"%s" is not a valid table name: use letters, digits and underscores, at most 63 characters.', $name));
            }
        }
    }

    /**
     * The same tables with a suffix, e.g. "_20261023120000" for the tables of a build.
     *
     * @throws InvalidArgumentException when the resulting names are not valid
     */
    public function withSuffix(string $suffix): self
    {
        return new self($this->houseNumbers.$suffix, $this->translations.$suffix, $this->metadata.$suffix);
    }
}
