<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Import\Photon\LineSourceInterface;

/**
 * Serves the given lines for any dump and records the dumps it was asked for.
 */
final class InMemoryLineSource implements LineSourceInterface
{
    /** @var list<string> */
    public array $dumps = [];

    /**
     * @param list<string> $lines
     */
    public function __construct(
        private readonly array $lines = [],
    ) {
    }

    public function lines(string $dump): iterable
    {
        $this->dumps[] = $dump;
        foreach ($this->lines as $index => $line) {
            yield $index + 1 => $line;
        }
    }
}
