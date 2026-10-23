<?php

namespace Tito10047\Geocoder\HouseNumber;

use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Receives the house numbers of an import.
 *
 * Implementations may buffer the house numbers: they are complete only once close() returned.
 */
interface HouseNumberWriterInterface
{
    /**
     * @throws RuntimeException when the house number cannot be written
     */
    public function write(HouseNumber $houseNumber): void;

    /**
     * Writes the buffered house numbers and releases the resources; nothing may be written afterwards.
     *
     * @throws RuntimeException when the house numbers cannot be written
     */
    public function close(): void;
}
