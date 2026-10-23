<?php

namespace Tito10047\Geocoder\Import\Download;

/**
 * Follows a download, e.g. to draw a progress bar.
 */
interface DownloadProgressInterface
{
    /**
     * Called once when the transfer starts, from time to time while it runs and once when it ends.
     *
     * @param string   $region          the region being downloaded
     * @param int      $downloadedBytes the bytes received so far
     * @param int|null $totalBytes      the size of the dump, null when the server does not tell
     */
    public function advance(string $region, int $downloadedBytes, ?int $totalBytes): void;
}
