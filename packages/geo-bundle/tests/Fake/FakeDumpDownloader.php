<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\Geocoder\Import\Download\DownloadProgressInterface;
use Tito10047\Geocoder\Import\Download\DownloadResult;
use Tito10047\Geocoder\Import\Download\DumpDownloaderInterface;

/**
 * Records the downloads the command asks for and answers without downloading anything.
 */
final class FakeDumpDownloader implements DumpDownloaderInterface
{
    /** @var list<array{string, string, bool}> the region, the directory and the force flag of every download */
    public array $downloads = [];

    /** @var list<string> the regions answered "not modified" */
    public array $unchanged = [];

    /** @var array<string, \Throwable> the failure of a region */
    public array $failures = [];

    public function download(string $region, string $directory, bool $force = false, ?DownloadProgressInterface $progress = null): DownloadResult
    {
        $this->downloads[] = [$region, $directory, $force];
        if (isset($this->failures[$region])) {
            throw $this->failures[$region];
        }
        $unchanged = \in_array($region, $this->unchanged, true);
        if (!$unchanged) {
            $progress?->advance($region, 0, 2048);
            $progress?->advance($region, 2048, 2048);
        }
        $name = substr($region, (int) strrpos($region, '/') + 1);

        return new DownloadResult($region, 'https://example.com/'.$region.'.jsonl.zst', $directory.'/'.$name.'.jsonl.zst', !$unchanged, 2048,
            new \DateTimeImmutable('2026-10-17T23:30:00+00:00'));
    }
}
