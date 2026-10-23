<?php

namespace Tito10047\Geocoder\Import;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexNamingInterface;
use Tito10047\Geocoder\Index\IndexPublisher;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Storage\BuildStorageInterface;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Imports dumps into a new Meilisearch index and new database tables, and publishes them.
 *
 * The index gets its settings before the first document and is compacted once complete; the
 * house numbers are loaded into tables without secondary indexes, created afterwards. The index
 * and the tables of a build share its suffix ("geo_20261023120000", "geo_house_20261023120000").
 */
final readonly class Importer implements ImporterInterface
{
    private IndexPublisher $publisher;

    /**
     * @param TableNames $tables the live tables; a build adds its suffix to them
     */
    public function __construct(
        private ConverterInterface $converter,
        private IndexManagerInterface $indexes,
        private BuildStorageInterface $storage,
        private IndexNamingInterface $naming = new TimestampIndexNaming(),
        private IndexSettings $settings = new IndexSettings(),
        private TableNames $tables = new TableNames(),
        private ClockInterface $clock = new SystemClock(),
        private LoggerInterface $logger = new NullLogger(),
    ) {
        $this->publisher = new IndexPublisher($indexes, $naming);
    }

    public function import(iterable $dumps, ?ImportProgressInterface $progress = null): Build
    {
        $dumps = [...$dumps];
        if ([] === $dumps) {
            throw new InvalidArgumentException('Nothing to import: give at least one dump.');
        }

        $now = $this->clock->now();
        // to the second, like the name of the build and the metadata
        $startedAt = $now->setTime((int) $now->format('G'), (int) $now->format('i'), (int) $now->format('s'));
        $uid = $this->naming->buildUid($startedAt);
        $tables = $this->tables->withSuffix($this->naming->buildSuffix($uid));
        if ($this->indexes->exists($uid) || $this->storage->exists($tables)) {
            throw new RuntimeException(\sprintf('The build %s exists already: was another import started in the same second?', $uid));
        }

        $indexCreated = false;
        $tablesCreated = false;
        try {
            // neither existed: whatever a failed creation leaves is ours to remove
            $indexCreated = true;
            $this->indexes->create($uid, $this->settings);
            $tablesCreated = true;
            $this->storage->create($tables);

            $documents = $this->indexes->documentWriter($uid);
            $houseNumbers = $this->storage->houseNumberWriter($tables);
            $translations = $this->storage->translationWriter($tables);
            $summaries = [];
            foreach ($dumps as $dump) {
                $this->logger->info('Importing the dump {dump} into the build {build}.', ['dump' => $dump, 'build' => $uid]);
                $summaries[] = $this->converter->convert($dump, $documents, $houseNumbers, $translations, $progress);
            }
            $documents->close();
            $houseNumbers->close();
            $translations->close();

            $build = new Build($uid, $tables, $startedAt, $this->settings->hash(), $this->storage->schemaVersion(), $summaries);
            $this->storage->complete($build);
            // the free pages left by indexing are about a fifth of the index
            $this->indexes->compact($uid);

            return $build;
        } catch (\Throwable $exception) {
            $this->discard($uid, $tables, $indexCreated, $tablesCreated);

            if ($exception instanceof ExceptionInterface || !$exception instanceof \Exception) {
                throw $exception;
            }

            throw new RuntimeException(\sprintf('The import of the build %s failed: %s', $uid, $exception->getMessage()), 0, $exception);
        }
    }

    public function publish(Build $build): Build
    {
        $uid = $build->indexUid;
        if (!$this->naming->isBuildUid($uid)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not the uid of a build of the index %s.', $uid, $this->naming->liveUid()));
        }
        // everything checked before the index changes: the tables come second
        $this->assertComplete($uid, $build->tables);

        $this->publisher->publish($uid);
        $this->storage->publish($build->tables, $this->tables);
        $this->logger->info('The build {build} is live.', ['build' => $uid]);

        // a build started later belongs to an import that may still run
        $keep = array_values(array_filter($this->buildUids(), static fn (string $other): bool => strcmp($other, $uid) > 0));
        $this->cleanup($keep);

        return $build->withTables($this->tables);
    }

    public function build(string $indexUid): Build
    {
        $tables = $this->tables->withSuffix($this->naming->buildSuffix($indexUid));
        $this->assertComplete($indexUid, $tables);

        return $this->storage->read($tables);
    }

    public function live(): Build
    {
        return $this->storage->read($this->tables);
    }

    public function cleanup(array $keep = []): array
    {
        $removed = [];
        foreach ($this->publisher->cleanup($keep) as $uid) {
            $removed[$uid] = true;
        }
        foreach ($this->tableBuildUids() as $uid => $suffix) {
            if (!\in_array($uid, $keep, true)) {
                $this->storage->drop($this->tables->withSuffix($suffix));
                $removed[$uid] = true;
            }
        }
        foreach (array_keys($removed) as $uid) {
            $this->logger->info('Removed the build {build}.', ['build' => $uid]);
        }

        $removed = array_map(strval(...), array_keys($removed));
        sort($removed);

        return $removed;
    }

    /**
     * @throws BuildNotFoundException when the index, the tables or their metadata of the build are missing
     */
    private function assertComplete(string $uid, TableNames $tables): void
    {
        if (!$this->indexes->exists($uid)) {
            throw new BuildNotFoundException(\sprintf('The index %s of the build does not exist: was it published or removed already?', $uid));
        }
        // the metadata is written last: tables without it are those of a failed import
        $stored = $this->storage->read($tables);
        if ($stored->indexUid !== $uid) {
            throw new BuildNotFoundException(\sprintf('The tables %s hold the build %s, not %s.', $tables->houseNumbers, $stored->indexUid, $uid));
        }
        if ($stored->schemaVersion !== $this->storage->schemaVersion()) {
            throw new RuntimeException(\sprintf('The build %s has the schema version %d; this version of the library uses %d: import the dumps again.', $uid, $stored->schemaVersion, $this->storage->schemaVersion()));
        }
    }

    /**
     * The builds with an index or tables.
     *
     * @return list<string>
     */
    private function buildUids(): array
    {
        $uids = array_keys($this->tableBuildUids());
        foreach ($this->indexes->indexUids() as $uid) {
            if ($this->naming->isBuildUid($uid)) {
                $uids[] = $uid;
            }
        }

        return array_values(array_unique(array_map(strval(...), $uids)));
    }

    /**
     * The builds with tables, and the suffix of their tables.
     *
     * @return array<string, string>
     */
    private function tableBuildUids(): array
    {
        $builds = [];
        foreach ($this->storage->suffixes($this->tables) as $suffix) {
            $uid = $this->naming->liveUid().$suffix;
            try {
                if ($this->naming->isBuildUid($uid) && $this->naming->buildSuffix($uid) === $suffix) {
                    $builds[$uid] = $suffix;
                }
            } catch (InvalidArgumentException) {
                // not a table of a build
            }
        }

        return $builds;
    }

    private function discard(string $uid, TableNames $tables, bool $indexCreated, bool $tablesCreated): void
    {
        $this->storage->rollBack();
        try {
            if ($indexCreated && $this->indexes->exists($uid)) {
                $this->indexes->delete($uid);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('The index {build} of the failed import cannot be deleted: {error}', ['build' => $uid, 'error' => $exception->getMessage(), 'exception' => $exception]);
        }
        try {
            if ($tablesCreated) {
                $this->storage->drop($tables);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('The tables of the failed import {build} cannot be dropped: {error}', ['build' => $uid, 'error' => $exception->getMessage(), 'exception' => $exception]);
        }
    }
}
