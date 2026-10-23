<?php

namespace Tito10047\Geocoder\Photon;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * One "osm_tag" filter of Photon: includes or excludes the features by their main OSM tag.
 *
 * "key:value", "key" and ":value" include, "!key:value", "!key" and ":!value" exclude. A feature
 * whose tag value is unknown (a street merged from ways) matches a filter on its key only.
 *
 * @see https://github.com/komoot/photon/blob/master/docs/api-v1.md#filter-results-by-tags-and-values
 */
final readonly class OsmTagFilter
{
    private const PATTERN = '/^(?:(?<negated>!?)(?<key>[^:!]+)(?::(?<value>[^:!]+))?|:(?<negatedValue>!?)(?<onlyValue>[^:!]+))$/D';

    /**
     * @param string|null $key      null: any key
     * @param string|null $value    null: any value
     * @param bool        $excludes whether the matching features are excluded rather than included
     *
     * @throws InvalidArgumentException when neither the key nor the value is given, or one is empty
     */
    public function __construct(
        public ?string $key,
        public ?string $value,
        public bool $excludes = false,
    ) {
        if ((null === $key && null === $value) || '' === $key || '' === $value) {
            throw new InvalidArgumentException('An OSM tag filter needs a key, a value or both.');
        }
    }

    /**
     * @throws InvalidArgumentException when the filter is not in one of the six forms of Photon
     */
    public static function fromString(string $filter): self
    {
        if (1 !== preg_match(self::PATTERN, $filter, $match)) {
            throw new InvalidArgumentException(\sprintf('The OSM tag filter must be "key:value", "key", ":value", "!key:value", "!key" or ":!value", "%s" given.', $filter));
        }
        // the groups of the second form are set only when it matched (unmatched trailing groups are left out)
        if (isset($match['onlyValue'])) {
            return new self(null, $match['onlyValue'], '!' === $match['negatedValue']);
        }

        return new self($match['key'], '' === ($match['value'] ?? '') ? null : $match['value'], '!' === $match['negated']);
    }

    /**
     * Whether the feature has the tag (the exclusion is not applied: see $excludes).
     */
    public function matches(Feature $feature): bool
    {
        return (null === $this->key || $this->key === $feature->osmKey)
            && (null === $this->value || $this->value === $feature->osmValue);
    }
}
