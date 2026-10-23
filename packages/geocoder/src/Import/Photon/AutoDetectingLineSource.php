<?php

namespace Tito10047\Geocoder\Import\Photon;

/**
 * Reads plain dumps as they are and decompresses ".zst" files: through ext-zstd when it is
 * loaded (the "compress.zstd://" stream), otherwise through the zstd command.
 */
final readonly class AutoDetectingLineSource implements LineSourceInterface
{
    private bool $zstdExtension;

    /**
     * @param LineSourceInterface $streams       reads plain files and stream URLs
     * @param LineSourceInterface $zstdProcess   decompresses ".zst" files without ext-zstd
     * @param bool|null           $zstdExtension whether ext-zstd is used, null to use it when it is loaded
     */
    public function __construct(
        private LineSourceInterface $streams = new StreamLineSource(),
        private LineSourceInterface $zstdProcess = new ZstdProcessLineSource(),
        ?bool $zstdExtension = null,
    ) {
        $this->zstdExtension = $zstdExtension ?? \extension_loaded('zstd');
    }

    public function lines(string $dump): iterable
    {
        // a stream URL ("php://stdin", "compress.zstd://…") is opened as it is
        if (str_contains($dump, '://') || !str_ends_with(strtolower($dump), '.zst')) {
            return $this->streams->lines($dump);
        }

        return $this->zstdExtension ? $this->streams->lines('compress.zstd://'.$dump) : $this->zstdProcess->lines($dump);
    }
}
