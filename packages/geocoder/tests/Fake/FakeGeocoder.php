<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Search\GeocoderInterface;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * Answers the suggestions it is given, up to the limit, and records the options it was asked with.
 */
final class FakeGeocoder implements GeocoderInterface
{
    /** @var list<array{string, SearchOptions}> */
    public array $calls = [];

    /**
     * @param list<Suggestion> $suggestions
     */
    public function __construct(
        private readonly array $suggestions = [],
    ) {
    }

    public function suggest(string $text, ?SearchOptions $options = null): array
    {
        $options ??= new SearchOptions();
        $this->calls[] = [$text, $options];

        return \array_slice($this->suggestions, 0, $options->limit);
    }

    public function geocode(string $text, ?SearchOptions $options = null): ?Suggestion
    {
        return $this->suggest($text, ($options ?? new SearchOptions())->withLimit(1))[0] ?? null;
    }
}
