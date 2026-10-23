<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\ImporterInterface;
use Tito10047\Geocoder\Import\ImportProgressInterface;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Records what the commands ask and answers the builds it is given.
 */
final class FakeImporter implements ImporterInterface
{
    public Build $build;

    /** The live build; none published when null. */
    public ?Build $live = null;

    /** @var list<string> */
    public array $removed = [];

    public ?\Throwable $importException = null;
    public ?\Throwable $publishException = null;
    public ?\Throwable $buildException = null;
    public ?\Throwable $liveException = null;
    public ?\Throwable $cleanupException = null;

    /** @var list<list<string>> */
    public array $imports = [];

    /** @var list<string> */
    public array $published = [];

    /** @var list<string> */
    public array $builds = [];

    /** @var list<list<string>> */
    public array $cleanups = [];

    public function __construct()
    {
        $this->build = Builds::build();
    }

    public function import(iterable $dumps, ?ImportProgressInterface $progress = null): Build
    {
        $dumps = array_values([...$dumps]);
        $this->imports[] = $dumps;
        foreach ($dumps as $dump) {
            $progress?->advance($dump, new ImportCounts(1, 2, 3, 4));
            $progress?->advance($dump, new ImportCounts(10, 20, 30, 40));
        }
        if ($this->importException instanceof \Throwable) {
            throw $this->importException;
        }

        return $this->build;
    }

    public function publish(Build $build): Build
    {
        $this->published[] = $build->indexUid;
        if ($this->publishException instanceof \Throwable) {
            throw $this->publishException;
        }

        return $build->withTables(new TableNames());
    }

    public function build(string $indexUid): Build
    {
        $this->builds[] = $indexUid;
        if ($this->buildException instanceof \Throwable) {
            throw $this->buildException;
        }

        return Builds::build($indexUid);
    }

    public function live(): Build
    {
        if ($this->liveException instanceof \Throwable) {
            throw $this->liveException;
        }

        return $this->live ?? throw new BuildNotFoundException('There is no build in the tables geo_house.');
    }

    public function cleanup(array $keep = []): array
    {
        $this->cleanups[] = $keep;
        if ($this->cleanupException instanceof \Throwable) {
            throw $this->cleanupException;
        }

        return $this->removed;
    }
}
