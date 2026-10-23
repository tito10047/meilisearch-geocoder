<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Search\RerankerInterface;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * Reverses the order, to show that the geocoder applies it; remembers what it received.
 */
final class ReversingReranker implements RerankerInterface
{
    /**
     * @var list<Suggestion>
     */
    public array $received = [];

    public function rerank(array $suggestions, SearchOptions $options): array
    {
        $this->received = $suggestions;

        return array_reverse($suggestions);
    }
}
