<?php

namespace Tito10047\Geocoder\Import\Download;

/**
 * What a download did: the dump of the region is in $path either way.
 */
final readonly class DownloadResult
{
    /**
     * @param string                  $region       the region: "europe/slovakia"
     * @param string                  $url          where the dump comes from
     * @param string                  $path         the dump in the directory
     * @param bool                    $downloaded   whether a new copy was downloaded; false when the server said the copy is current
     * @param int                     $bytes        the size of the dump
     * @param \DateTimeImmutable|null $lastModified when the server says the dump was last changed, null when it does not
     */
    public function __construct(
        public string $region,
        public string $url,
        public string $path,
        public bool $downloaded,
        public int $bytes,
        public ?\DateTimeImmutable $lastModified = null,
    ) {
    }
}
