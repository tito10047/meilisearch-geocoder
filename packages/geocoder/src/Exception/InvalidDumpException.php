<?php

namespace Tito10047\Geocoder\Exception;

/**
 * Thrown when a dump is not a Photon dump the geocoder can read: invalid JSON, a missing header,
 * an unsupported version or a record without a required field.
 */
final class InvalidDumpException extends InvalidArgumentException
{
    /**
     * @param string $dump     the dump, as given to the reader
     * @param int    $dumpLine the line of the dump (1-based) where the problem is, 0 when it is not a line
     */
    public function __construct(
        public readonly string $dump,
        public readonly int $dumpLine,
        string $reason,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $dumpLine > 0 ? \sprintf('Line %d of the dump "%s": %s', $dumpLine, $dump, $reason) : \sprintf('The dump "%s": %s', $dump, $reason),
            0,
            $previous,
        );
    }
}
