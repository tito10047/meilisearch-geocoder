<?php

namespace Tito10047\Geocoder\Import\Download;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Download\Exception\DownloadException;

/**
 * Downloads the Photon dump of a region into a directory, again only when it changed.
 */
interface DumpDownloaderInterface
{
    /**
     * Downloads the dump of the region unless the copy in the directory is still the current one.
     *
     * The file appears complete or not at all (it is downloaded beside and renamed), and is a
     * Photon dump: its header was read before it replaced the previous copy.
     *
     * @param string $region    the region as the download server names it: "europe/slovakia"
     * @param string $directory where the dump goes; created when missing
     * @param bool   $force     download even when the copy is current
     *
     * @throws InvalidArgumentException when the region is not a valid name or the directory is not one
     * @throws DownloadException        when the download fails or what arrived is not a Photon dump (the copy is left as it was)
     */
    public function download(string $region, string $directory, bool $force = false, ?DownloadProgressInterface $progress = null): DownloadResult;
}
