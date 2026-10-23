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

    /**
     * Publishes in this order, each step resumable:
     *
     * 1. checks the build (its index, its tables and their metadata, the schema version);
     * 2. records the fingerprint of the build index in the metadata of its tables, once;
     * 3. swaps the build index in under the live uid, unless the live index has that fingerprint
     *    already (a former run swapped it in);
     * 4. renames the build tables to the live names; when that fails right after the swap of
     *    this run, the swap is undone, so that the index and the tables stay of one build;
     * 5. deletes the replaced live index (left under the build uid by the swap);
     * 6. removes the builds started before this one.
     *
     * A run that stopped anywhere (a failure, a timeout, a killed process) leaves a state the next
     * run with the same build converges from: before step 3 nothing changed; between steps 3 and
     * 4 the fingerprint tells that the index is live and the tables follow; after step 4 the
     * build is in the live tables (pass the live build, see build()) and steps 5 and 6 finish.
     */
    public function publish(Build $build): Build
    {
        $uid = $build->indexUid;
        if (!$this->naming->isBuildUid($uid)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not the uid of a build of the index %s.', $uid, $this->naming->liveUid()));
        }
        // everything checked before the index changes: the tables come second
        $fingerprint = $this->assertComplete($uid, $build->tables);
        $this->storage->recordPublication($build->tables, $fingerprint);
        $tablesLive = $build->tables == $this->tables;

        $swapped = false;
        if ($this->indexes->fingerprint($this->naming->liveUid()) !== $fingerprint) {
            $this->publisher->swapIn($uid);
            $swapped = true;
        } else {
            $this->logger->info('The index of the build {build} is live already: finishing its publication.', ['build' => $uid]);
        }

        if (!$tablesLive) {
            try {
                $this->storage->publish($build->tables, $this->tables);
            } catch (\Throwable $exception) {
                if ($swapped) {
                    $this->swapBack($uid, $exception);
                }

                throw $exception;
            }
        }

        $this->publisher->removeReplaced($uid);
        $this->logger->info('The build {build} is live.', ['build' => $uid]);

        // a build started later belongs to an import that may still run
        $keep = array_values(array_filter($this->buildUids(), static fn (string $other): bool => strcmp($other, $uid) > 0));
        $this->cleanup($keep);

        return $build->withTables($this->tables);
    }

    /**
     * Also the build whose publication stopped after its index was swapped in: its index is live,
     * its tables are still those of the build; publishing it again finishes the publication.
     */
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
     * Checks that the tables hold the complete build and that its index exists, under the build
     * uid or (when its publication was started) as the live index.
     *
     * @return string the fingerprint of the build index: the one recorded when its publication started, if it did
     *
     * @throws BuildNotFoundException when the index, the tables or their metadata of the build are missing
     */
    private function assertComplete(string $uid, TableNames $tables): string
    {
        // the metadata is written last: tables without it are those of a failed import
        $stored = $this->storage->read($tables);
        if ($stored->indexUid !== $uid) {
            throw new BuildNotFoundException(\sprintf('The tables %s hold the build %s, not %s.', $tables->houseNumbers, $stored->indexUid, $uid));
        }
        if ($stored->schemaVersion !== $this->storage->schemaVersion()) {
            throw new RuntimeException(\sprintf('The build %s has the schema version %d; this version of the library uses %d: import the dumps again.', $uid, $stored->schemaVersion, $this->storage->schemaVersion()));
        }

        $recorded = $this->storage->recordedPublication($tables);
        if (null !== $recorded) {
            // a publication was started: the index is under the build uid or live, wherever the swap left it
            if ($this->indexes->fingerprint($uid) !== $recorded && $this->indexes->fingerprint($this->naming->liveUid()) !== $recorded) {
                throw new BuildNotFoundException(\sprintf('The index of the build %s exists neither under its uid nor as the live index %s: was it removed?', $uid, $this->naming->liveUid()));
            }

            return $recorded;
        }

        $fingerprint = $this->indexes->fingerprint($uid);
        if (null === $fingerprint) {
            throw new BuildNotFoundException(\sprintf('The index %s of the build does not exist: was it published or removed already?', $uid));
        }

        return $fingerprint;
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

    /**
     * Undoes the swap of a publication whose tables could not be published.
     */
    private function swapBack(string $uid, \Throwable $cause): void
    {
        try {
            $this->publisher->swapBack($uid);
            $this->logger->warning('The tables of the build {build} cannot be published: its index was swapped back out.', ['build' => $uid, 'error' => $cause->getMessage(), 'exception' => $cause]);
        } catch (\Throwable $exception) {
            // the next publication of the build finds its index live and publishes the tables
            $this->logger->error('The tables of the build {build} cannot be published and its index cannot be swapped back out: publish the build again to finish. {error}', ['build' => $uid, 'error' => $exception->getMessage(), 'exception' => $exception]);
        }
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
