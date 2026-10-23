<?php

namespace Tito10047\Geocoder\Artifact;

use Tito10047\Geocoder\Import\Build;

/**
 * What an installation did: the tables are live, the snapshot waits for the restart of the
 * Meilisearch instance.
 */
final readonly class InstalledArtifact
{
    /**
     * @param string $snapshotPath the snapshot in the data directory of the instance, to start it with: meilisearch --import-snapshot <path as the instance sees it>
     * @param Build  $build        the build of the artifact, in the live tables
     */
    public function __construct(
        public ArtifactManifest $manifest,
        public string $snapshotPath,
        public Build $build,
    ) {
    }
}
