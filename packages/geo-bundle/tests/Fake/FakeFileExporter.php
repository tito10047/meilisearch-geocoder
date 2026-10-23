<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\File\FileExporterInterface;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\ImportProgressInterface;

/**
 * Records the exports asked and answers a build, writing nothing.
 */
final class FakeFileExporter implements FileExporterInterface
{
    public ?\Throwable $exception = null;

    /** @var list<array{list<string>, string}> dumps, directory */
    public array $exports = [];

    public function export(iterable $dumps, string $directory, ?ImportProgressInterface $progress = null): Build
    {
        $dumps = array_values([...$dumps]);
        $this->exports[] = [$dumps, $directory];
        foreach ($dumps as $dump) {
            $progress?->advance($dump, new ImportCounts(1, 2, 3, 4));
        }
        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return Builds::build();
    }
}
