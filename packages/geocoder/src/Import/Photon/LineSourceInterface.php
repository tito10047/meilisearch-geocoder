<?php

namespace Tito10047\Geocoder\Import\Photon;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\IoException;

/**
 * Reads a dump line by line: a file, a stream, the output of a decompressor, ….
 *
 * A dump is several gigabytes: implementations stream it, they never hold more than a line.
 */
interface LineSourceInterface
{
    /**
     * The lines of the dump, lazily, without their line endings; empty lines are skipped.
     *
     * The dump is opened by this call, so that a missing dump fails at once, and closed when the
     * iteration ends or the iterable is released.
     *
     * @param string $dump a file path or a stream URL
     *
     * @return iterable<int, string> the line number (1-based) => the line
     *
     * @throws InvalidArgumentException when the dump cannot be opened
     * @throws IoException              when reading the dump fails
     */
    public function lines(string $dump): iterable;
}
