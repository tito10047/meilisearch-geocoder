<?php

namespace Tito10047\Geocoder\Artifact;

use Tito10047\Geocoder\Artifact\Exception\ArtifactException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\File\FileExportLayout;
use Tito10047\Geocoder\Import\File\LocalFiles;
use Tito10047\Geocoder\Index\IndexNamingInterface;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\Pdo\PdoSchema;
use Tito10047\Geocoder\Storage\Pdo\PdoTableCopier;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Installs artifacts: the "separate instance" deployment, where a small server never indexes.
 *
 * Steps on the server:
 *
 * 1. stop the Meilisearch instance of the geocoder and empty its data directory (or use a new one);
 * 2. install(): verifies the artifact, copies the snapshot into the data directory (as
 *    "geocoder.snapshot") and makes the tables live in the database of the application;
 * 3. start the instance with `--import-snapshot /meili_data/geocoder.snapshot` (the path inside
 *    the container), of the same version as the one that built the artifact; once it is up, the
 *    snapshot can be deleted. The master key is the one the instance is started with.
 *
 * The tables are loaded into new tables (with the suffix of the build) and renamed to the live
 * names at once, like a publication.
 */
final readonly class ArtifactInstaller implements ArtifactInstallerInterface
{
    public const SNAPSHOT_FILE = 'geocoder.snapshot';

    /**
     * @param string     $meilisearchVersion the version of the target instance ("1.35.1"): the snapshot must be of the same
     * @param TableNames $tables             the live tables in the database of the application
     * @param string     $databaseName       the database directory of Meilisearch in its data directory ("data.ms", MEILI_DB_PATH)
     *
     * @throws InvalidArgumentException when a value is empty
     */
    public function __construct(
        private string $meilisearchVersion,
        private IndexNamingInterface $naming = new TimestampIndexNaming(),
        private TableNames $tables = new TableNames(),
        private string $databaseName = 'data.ms',
    ) {
        if ('' === $meilisearchVersion || '' === $databaseName) {
            throw new InvalidArgumentException('The Meilisearch version and the name of its database must not be empty.');
        }
    }

    public function install(string $artifactDirectory, string $meilisearchDataDirectory, \PDO $database): InstalledArtifact
    {
        $manifest = $this->verify($artifactDirectory);
        $artifact = new Artifact($artifactDirectory, $manifest);

        if (!is_dir($meilisearchDataDirectory)) {
            throw new InvalidArgumentException(\sprintf('The Meilisearch data directory "%s" does not exist.', $meilisearchDataDirectory));
        }
        $meilisearchDatabase = $meilisearchDataDirectory.'/'.$this->databaseName;
        if (file_exists($meilisearchDatabase)) {
            throw new ArtifactException(\sprintf('The Meilisearch data directory "%s" has a database (%s): Meilisearch imports a snapshot only without one. Stop the instance and remove it (or use a new data directory) first.', $meilisearchDataDirectory, $this->databaseName));
        }

        // the snapshot first: it fails on a full disk or a wrong directory before anything changes
        $snapshotPath = $meilisearchDataDirectory.'/'.self::SNAPSHOT_FILE;
        $partial = $snapshotPath.'.part';
        LocalFiles::removeQuietly([$partial]);
        $hash = LocalFiles::copy($artifact->snapshotPath(), $partial);
        if ($hash !== $manifest->snapshot->sha256) {
            LocalFiles::removeQuietly([$partial]);
            throw new ArtifactException(\sprintf('The snapshot changed while it was copied to "%s".', $partial));
        }
        LocalFiles::call(static fn (): bool => rename($partial, $snapshotPath), \sprintf('Cannot rename "%s" to "%s"', $partial, $snapshotPath));

        $build = $this->installTables($artifact, $database);

        return new InstalledArtifact($manifest, $snapshotPath, $build);
    }

    /**
     * @throws ArtifactException when a check fails
     */
    private function verify(string $directory): ArtifactManifest
    {
        if (!is_dir($directory) || !is_file($directory.'/'.Artifact::MANIFEST)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not an artifact: it has no %s.', $directory, Artifact::MANIFEST));
        }
        try {
            $manifest = ArtifactManifest::fromJson(LocalFiles::read($directory.'/'.Artifact::MANIFEST));
        } catch (InvalidArgumentException $exception) {
            throw new ArtifactException(\sprintf('The artifact "%s" cannot be installed: %s', $directory, $exception->getMessage()), 0, $exception);
        }

        if ($manifest->meilisearchVersion !== $this->meilisearchVersion) {
            throw new ArtifactException(\sprintf('The snapshot of the artifact is of Meilisearch %s; the instance is %s, and Meilisearch loads only snapshots of its own version.', $manifest->meilisearchVersion, $this->meilisearchVersion));
        }
        if (PdoSchema::VERSION !== $manifest->schemaVersion) {
            throw new ArtifactException(\sprintf('The tables of the artifact have the schema version %d; this version of the library uses %d: build the artifact again.', $manifest->schemaVersion, PdoSchema::VERSION));
        }
        if ($manifest->liveUid !== $this->naming->liveUid()) {
            throw new ArtifactException(\sprintf('The index of the artifact is %s, the application searches %s.', $manifest->liveUid, $this->naming->liveUid()));
        }
        if (!$this->naming->isBuildUid($manifest->indexUid)) {
            throw new ArtifactException(\sprintf('The build %s of the artifact is not named like the builds of the index %s.', $manifest->indexUid, $this->naming->liveUid()));
        }

        foreach ([$manifest->snapshot, $manifest->database] as $file) {
            $path = $directory.'/'.$file->name;
            if (!is_file($path)) {
                throw new ArtifactException(\sprintf('The file %s of the artifact is missing.', $file->name));
            }
            if (LocalFiles::size($path) !== $file->size || LocalFiles::sha256($path) !== $file->sha256) {
                throw new ArtifactException(\sprintf('The file %s of the artifact is corrupt: its size or checksum differs from the manifest.', $file->name));
            }
        }

        return $manifest;
    }

    private function installTables(Artifact $artifact, \PDO $database): Build
    {
        try {
            $source = new \PDO('sqlite:'.$artifact->databasePath(), options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        } catch (\PDOException $exception) {
            throw new StorageException(\sprintf('The database of the artifact cannot be opened: %s', $exception->getMessage()), 0, $exception);
        }

        $storage = new PdoBuildStorage($database);
        $buildTables = $this->tables->withSuffix($this->naming->buildSuffix($artifact->manifest->indexUid));
        // left by an installation of the same artifact that failed
        $storage->drop($buildTables);

        $build = (new PdoTableCopier($database))->copy($source, FileExportLayout::tables(), $buildTables);
        if ($build->indexUid !== $artifact->manifest->indexUid) {
            $storage->drop($buildTables);
            throw new ArtifactException(\sprintf('The database of the artifact holds the build %s, its manifest %s.', $build->indexUid, $artifact->manifest->indexUid));
        }
        try {
            $storage->publish($buildTables, $this->tables);
        } catch (\Throwable $exception) {
            try {
                $storage->drop($buildTables);
            } catch (StorageException) {
                // the original failure matters more
            }

            throw $exception;
        }

        return $build->withTables($this->tables);
    }
}
