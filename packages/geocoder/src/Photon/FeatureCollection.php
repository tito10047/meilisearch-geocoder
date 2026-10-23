<?php

namespace Tito10047\Geocoder\Photon;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * The answer of the Photon API: a GeoJSON FeatureCollection.
 */
final readonly class FeatureCollection implements \JsonSerializable, \Countable
{
    /**
     * @param list<Feature> $features the best match first
     *
     * @throws InvalidArgumentException when the features are not a list of Feature
     */
    public function __construct(
        public array $features = [],
    ) {
        // the PHPDoc is not trusted: callers may not be analysed
        if (!array_is_list($features)) {
            throw new InvalidArgumentException('The features must be a list.');
        }
        if ([] !== array_filter($features, static fn (mixed $feature): bool => !$feature instanceof Feature)) {
            throw new InvalidArgumentException(\sprintf('The features must be a list of %s.', Feature::class));
        }
    }

    public function count(): int
    {
        return \count($this->features);
    }

    /**
     * @return array{type: 'FeatureCollection', features: list<Feature>}
     */
    public function jsonSerialize(): array
    {
        return ['type' => 'FeatureCollection', 'features' => $this->features];
    }
}
