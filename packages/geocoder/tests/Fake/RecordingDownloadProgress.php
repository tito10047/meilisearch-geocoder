<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Import\Download\DownloadProgressInterface;

/**
 * Remembers the progress of the downloads.
 */
final class RecordingDownloadProgress implements DownloadProgressInterface
{
    /**
     * @var list<array{string, int, int|null}>
     */
    public array $calls = [];

    public function advance(string $region, int $downloadedBytes, ?int $totalBytes): void
    {
        $this->calls[] = [$region, $downloadedBytes, $totalBytes];
    }
}
