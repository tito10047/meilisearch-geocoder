<?php

namespace Tito10047\Geocoder\Artifact;

use Tito10047\Geocoder\Artifact\Exception\ArtifactException;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\ClockInterface;
use Tito10047\Geocoder\Import\File\FileExportLayout;
use Tito10047\Geocoder\Import\File\LocalFiles;
use Tito10047\Geocoder\Import\SystemClock;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexNamingInterface;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Storage\Pdo\Dialect\TablePairs;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\Pdo\PdoErrorMode;
use Tito10047\Geocoder\Storage\Pdo\PdoTableCopier;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Builds artifacts on the instance that built the index.
 *
 * Meilisearch writes its snapshot into its own snapshot directory (MEILI_SNAPSHOT_DIR, by default
 * "snapshots/" next to its database); the builder needs that directory as a local path to pick
 * the snapshot up. The database becomes an SQLite file under the default table names: a copy of
 * the database itself (VACUUM INTO, consistent) when it is an SQLite database of the geocoder
 * only, a copy of the rows otherwise.
 */
final readonly class ArtifactBuilder implements ArtifactBuilderInterface
{
    public const SNAPSHOT_FILE = 'meilisearch.snapshot';

    /**
     * @param \PDO       $database          the database of the live tables
     * @param string     $snapshotDirectory where the Meilisearch instance writes its snapshots, as a local path
     * @param TableNames $tables            the live tables
     *
     * @throws InvalidArgumentException when the connection does not throw its errors
     */
    public function __construct(
        private IndexManagerInterface $indexes,
        private \PDO $database,
        private string $snapshotDirectory,
        private IndexNamingInterface $naming = new TimestampIndexNaming(),
        private TableNames $tables = new TableNames(),
        private ClockInterface $clock = new SystemClock(),
    ) {
        PdoErrorMode::assertExceptions($database);
    }

    public function build(Build $build, string $directory): Artifact
    {
        $liveUid = $this->naming->liveUid();
        $live = (new PdoBuildStorage($this->database))->read($this->tables);
        if ($live->indexUid !== $build->indexUid) {
            throw new ArtifactException(\sprintf('The build %s is not live (the live build is %s): publish it first.', $build->indexUid, $live->indexUid));
        }
        $others = array_values(array_diff($this->indexes->indexUids(), [$liveUid]));
        if ([] !== $others) {
            throw new ArtifactException(\sprintf('The snapshot would hold the other indexes of the instance too (%s): build artifacts on an instance holding only the index %s.', implode(', ', $others), $liveUid));
        }
        $status = $this->indexes->status($liveUid);
        if ($status->settingsHash !== $live->settingsHash) {
            throw new ArtifactException(\sprintf('The settings of the index %s are not those the build was made with.', $liveUid));
        }
        if (!is_dir($this->snapshotDirectory)) {
            throw new ArtifactException(\sprintf('The snapshot directory "%s" of the Meilisearch instance does not exist (or is not reachable from here).', $this->snapshotDirectory));
        }

        $created = LocalFiles::prepareEmptyDirectory($directory);
        $databaseFile = $directory.'/'.FileExportLayout::DATABASE;
        $snapshotFile = $directory.'/'.self::SNAPSHOT_FILE;
        try {
            $this->copyDatabase($databaseFile);
            $snapshotHash = LocalFiles::copy($this->snapshot(), $snapshotFile);

            $artifact = new Artifact($directory, new ArtifactManifest(
                meilisearchVersion: $status->meilisearchVersion,
                settingsHash: $live->settingsHash,
                schemaVersion: $live->schemaVersion,
                indexUid: $live->indexUid,
                liveUid: $liveUid,
                startedAt: $live->startedAt,
                createdAt: $this->clock->now(),
                documents: $status->documents,
                counts: $live->counts(),
                dataTimestamps: $live->dataTimestamps(),
                snapshot: new ArtifactFile(self::SNAPSHOT_FILE, LocalFiles::size($snapshotFile), $snapshotHash),
                database: new ArtifactFile(FileExportLayout::DATABASE, LocalFiles::size($databaseFile), LocalFiles::sha256($databaseFile)),
            ));
            LocalFiles::write($artifact->manifestPath(), $artifact->manifest->toJson());

            return $artifact;
        } catch (\Throwable $exception) {
            LocalFiles::removeQuietly([$databaseFile, $snapshotFile, $directory.'/'.Artifact::MANIFEST], $created ? $directory : null);

            if ($exception instanceof ExceptionInterface || !$exception instanceof \Exception) {
                throw $exception;
            }

            throw new RuntimeException(\sprintf('Building the artifact in "%s" failed: %s', $directory, $exception->getMessage()), 0, $exception);
        }
    }

    private function copyDatabase(string $file): void
    {
        if ($this->isDedicatedSqlite()) {
            try {
                // a consistent copy of the whole file, compacted, even while it is read
                $this->database->prepare('VACUUM INTO ?')->execute([$file]);
            } catch (\PDOException $exception) {
                throw new StorageException(\sprintf('Copying the database into "%s" failed: %s', $file, $exception->getMessage()), 0, $exception);
            }

            return;
        }

        try {
            $target = new \PDO('sqlite:'.$file, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $target->exec('PRAGMA journal_mode = OFF');
            $target->exec('PRAGMA synchronous = OFF');
        } catch (\PDOException $exception) {
            throw new StorageException(\sprintf('Cannot create the database "%s": %s', $file, $exception->getMessage()), 0, $exception);
        }
        (new PdoTableCopier($target))->copy($this->database, $this->tables, FileExportLayout::tables());
    }

    /**
     * Whether the database is an SQLite database holding only the live tables, under the names of
     * an artifact: then the artifact is a copy of it.
     */
    private function isDedicatedSqlite(): bool
    {
        if ('sqlite' !== $this->database->getAttribute(\PDO::ATTR_DRIVER_NAME) || $this->tables != FileExportLayout::tables()) {
            return false;
        }
        try {
            $statement = $this->database->query("SELECT name FROM sqlite_master WHERE type = 'table'", \PDO::FETCH_COLUMN, 0);
            $tables = false === $statement ? [] : array_map(strval(...), $statement->fetchAll());
        } catch (\PDOException $exception) {
            throw new StorageException(\sprintf('Listing the tables of the database failed: %s', $exception->getMessage()), 0, $exception);
        }
        foreach ($tables as $table) {
            if (!TablePairs::contains([$this->tables->houseNumbers, $this->tables->translations, $this->tables->metadata], $table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Has the instance create a snapshot and returns the file it wrote.
     */
    private function snapshot(): string
    {
        $before = $this->snapshotFiles();
        $this->indexes->snapshot();
        $after = $this->snapshotFiles();

        // Meilisearch replaces the file of the previous snapshot: a new file or a changed one is ours
        $new = array_filter($after, static fn (array $signature, string $file): bool => ($before[$file] ?? null) !== $signature, \ARRAY_FILTER_USE_BOTH);
        if ([] === $new) {
            throw new ArtifactException(\sprintf('The Meilisearch instance created a snapshot, but no new snapshot appeared in "%s": is it the snapshot directory of this instance (MEILI_SNAPSHOT_DIR)?', $this->snapshotDirectory));
        }
        uasort($new, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

        return array_key_first($new);
    }

    /**
     * @return array<string, array{int, int, int}> file => [modification time, size, inode]
     */
    private function snapshotFiles(): array
    {
        clearstatcache();
        $files = [];
        foreach (glob($this->snapshotDirectory.'/*.snapshot') ?: [] as $file) {
            $stat = @stat($file);
            if (false !== $stat && is_file($file)) {
                $files[$file] = [$stat['mtime'], $stat['size'], $stat['ino']];
            }
        }

        return $files;
    }
}
