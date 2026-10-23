<?php

namespace Tito10047\Geocoder\Artifact;

use Tito10047\Geocoder\Artifact\Exception\ArtifactException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Import\Build;

/**
 * Packs a published build into an artifact, to move it to another server without indexing there.
 */
interface ArtifactBuilderInterface
{
    /**
     * Snapshots the Meilisearch instance of the build, copies its tables into an SQLite database
     * and writes the manifest, into the directory.
     *
     * The build must be live (published) on a Meilisearch instance holding no other index: the
     * snapshot holds the whole instance.
     *
     * @param string $directory a directory that does not exist or is empty
     *
     * @throws InvalidArgumentException when the directory is not empty
     * @throws ArtifactException        when the build is not live or the instance holds other indexes
     * @throws RuntimeException         when the snapshot or the copy fails (the files written are removed)
     */
    public function build(Build $build, string $directory): Artifact;
}
