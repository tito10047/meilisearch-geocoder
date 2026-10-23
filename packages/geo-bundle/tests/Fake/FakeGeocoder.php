<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\Geocoder\Search\GeocoderInterface;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * Answers the suggestions it is given and records what it was asked.
 */
final class FakeGeocoder implements GeocoderInterface
{
    /** @var list<Suggestion> */
    public array $suggestions = [];

    public ?\Throwable $exception = null;

    /** @var list<array{string, SearchOptions|null}> */
    public array $calls = [];

    public function suggest(string $text, ?SearchOptions $options = null): array
    {
        $this->calls[] = [$text, $options];
        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return \array_slice($this->suggestions, 0, $options->limit ?? 5);
    }

    public function geocode(string $text, ?SearchOptions $options = null): ?Suggestion
    {
        return $this->suggest($text, ($options ?? new SearchOptions())->withLimit(1))[0] ?? null;
    }
}
