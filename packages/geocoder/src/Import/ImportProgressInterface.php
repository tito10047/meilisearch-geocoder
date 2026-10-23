<?php

namespace Tito10047\Geocoder\Import;

/**
 * Follows a long import, e.g. to draw a progress bar.
 */
interface ImportProgressInterface
{
    /**
     * Called from time to time while a dump is read, and once when it is finished.
     *
     * @param string       $dump   the dump being read, as given to the import
     * @param ImportCounts $counts what the dump has produced so far
     */
    public function advance(string $dump, ImportCounts $counts): void;
}
