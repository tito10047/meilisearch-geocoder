<?php

namespace Tito10047\Geocoder\Artifact;

use Tito10047\Geocoder\Artifact\Exception\ArtifactException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Installs an artifact on a server: its tables into the database of the application, its
 * snapshot into the data directory of a new, empty Meilisearch instance.
 */
interface ArtifactInstallerInterface
{
    /**
     * Verifies the artifact (checksums, versions), places its snapshot into the data directory of
     * the Meilisearch instance and makes its tables live in the database.
     *
     * Meilisearch loads a snapshot only when it starts without a database, and only one of its own
     * version: the instance must then be (re)started with --import-snapshot and the path of the
     * snapshot (InstalledArtifact::$snapshotPath, as the instance sees it).
     *
     * @param string $artifactDirectory        the directory of the artifact
     * @param string $meilisearchDataDirectory the data directory of the instance, without a database yet
     *
     * @throws InvalidArgumentException when a directory does not exist or the artifact is invalid
     * @throws ArtifactException        when a checksum or a version does not match, or the instance has a database
     * @throws RuntimeException         when copying the snapshot or the tables fails
     */
    public function install(string $artifactDirectory, string $meilisearchDataDirectory, \PDO $database): InstalledArtifact;
}
