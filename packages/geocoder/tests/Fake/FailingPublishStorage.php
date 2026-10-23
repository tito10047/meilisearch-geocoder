<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\HouseNumber\HouseNumberWriterInterface;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Name\TranslationWriterInterface;
use Tito10047\Geocoder\Storage\BuildStorageInterface;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * A build storage whose next publications fail, as when the database goes away in the middle of
 * a publication; everything else goes to the real storage.
 */
final class FailingPublishStorage implements BuildStorageInterface
{
    /** @var int how many of the next publications fail */
    public int $publishFailures = 0;

    public function __construct(private readonly BuildStorageInterface $storage)
    {
    }

    public function schemaVersion(): int
    {
        return $this->storage->schemaVersion();
    }

    public function create(TableNames $tables): void
    {
        $this->storage->create($tables);
    }

    public function houseNumberWriter(TableNames $tables): HouseNumberWriterInterface
    {
        return $this->storage->houseNumberWriter($tables);
    }

    public function translationWriter(TableNames $tables): TranslationWriterInterface
    {
        return $this->storage->translationWriter($tables);
    }

    public function complete(Build $build): void
    {
        $this->storage->complete($build);
    }

    public function read(TableNames $tables): Build
    {
        return $this->storage->read($tables);
    }

    public function exists(TableNames $tables): bool
    {
        return $this->storage->exists($tables);
    }

    public function recordPublication(TableNames $tables, string $indexFingerprint): void
    {
        $this->storage->recordPublication($tables, $indexFingerprint);
    }

    public function recordedPublication(TableNames $tables): ?string
    {
        return $this->storage->recordedPublication($tables);
    }

    public function publish(TableNames $build, TableNames $live): void
    {
        if ($this->publishFailures > 0) {
            --$this->publishFailures;

            throw new StorageException('The database went away.');
        }
        $this->storage->publish($build, $live);
    }

    public function drop(TableNames $tables): void
    {
        $this->storage->drop($tables);
    }

    public function suffixes(TableNames $tables): array
    {
        return $this->storage->suffixes($tables);
    }

    public function rollBack(): void
    {
        $this->storage->rollBack();
    }
}
