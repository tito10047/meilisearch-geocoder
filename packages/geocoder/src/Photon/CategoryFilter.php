<?php

namespace Tito10047\Geocoder\Photon;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * One "include" or "exclude" parameter of Photon: a comma-separated list of categories.
 *
 * An include parameter keeps a feature having any of its categories, an exclude parameter drops a
 * feature having all of them. A category is a path of 2 to 5 labels ("osm.tourism.hotel") matching
 * every category it is a prefix of. The features carry the reserved group "osm" only (their main
 * tag): a filter on another group matches nothing.
 *
 * @see https://github.com/komoot/photon/blob/master/docs/categories.md
 */
final readonly class CategoryFilter
{
    private const CATEGORY = '/^[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+){1,4}$/D';

    /**
     * @param list<list<string>> $categories the labels of each category
     *
     * @throws InvalidArgumentException when there is no category or one is not a path of 2 to 5 labels
     */
    public function __construct(
        public array $categories,
    ) {
        if ([] === $categories || !array_is_list($categories)) {
            throw new InvalidArgumentException('A category filter needs at least one category.');
        }
        foreach ($categories as $labels) {
            if (!\is_array($labels) || !array_is_list($labels) || 1 !== preg_match(self::CATEGORY, implode('.', $labels))) {
                throw new InvalidArgumentException('A category must be a path of 2 to 5 labels of letters, digits, "_" and "-".');
            }
        }
    }

    /**
     * "osm.tourism.hotel,osm.railway".
     *
     * @throws InvalidArgumentException when a category is invalid
     */
    public static function fromString(string $filter): self
    {
        $categories = [];
        foreach (explode(',', $filter) as $category) {
            if (1 !== preg_match(self::CATEGORY, $category)) {
                throw new InvalidArgumentException(\sprintf('A category must be a path of 2 to 5 labels separated by dots ("osm.tourism.hotel"), "%s" given.', $category));
            }
            $categories[] = explode('.', $category);
        }

        return new self($categories);
    }

    /**
     * Whether the feature has any of the categories (an include parameter).
     */
    public function matchesAny(Feature $feature): bool
    {
        foreach ($this->categories as $category) {
            if ($this->has($feature, $category)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the feature has all the categories (an exclude parameter).
     */
    public function matchesAll(Feature $feature): bool
    {
        foreach ($this->categories as $category) {
            if (!$this->has($feature, $category)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $category
     */
    private function has(Feature $feature, array $category): bool
    {
        foreach ($feature->categories() as $labels) {
            if (\array_slice($labels, 0, \count($category)) === $category) {
                return true;
            }
        }

        return false;
    }
}
