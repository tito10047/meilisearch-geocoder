<?php

namespace Tito10047\Geocoder\Import\Download;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Where the dump of a region is downloaded from, and the name of its file.
 */
interface RegionUrlResolverInterface
{
    /**
     * The URL of the dump of the region.
     *
     * @param string $region "europe/slovakia"
     *
     * @throws InvalidArgumentException when the region is not a valid name
     */
    public function url(string $region): string;

    /**
     * The name of the file the dump is saved as: "slovakia.jsonl.zst".
     *
     * @throws InvalidArgumentException when the region is not a valid name
     */
    public function fileName(string $region): string;
}
