<?php

namespace Tito10047\Geocoder\Import;

/**
 * The current time of an import: it names the build.
 *
 * The method is the one of PSR-20 (psr/clock), which the library does not require for one
 * method: a PSR-20 clock (Symfony's, a test clock) is adapted in one line.
 */
interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
