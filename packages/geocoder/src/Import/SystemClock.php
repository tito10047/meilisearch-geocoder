<?php

namespace Tito10047\Geocoder\Import;

/**
 * The time of the system, in UTC.
 */
final readonly class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
