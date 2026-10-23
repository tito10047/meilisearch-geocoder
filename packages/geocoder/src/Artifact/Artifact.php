<?php

namespace Tito10047\Geocoder\Artifact;

/**
 * A built artifact: a directory with the Meilisearch snapshot, the SQLite database of the tables
 * and the manifest.json describing them.
 */
final readonly class Artifact
{
    public const MANIFEST = 'manifest.json';

    public function __construct(
        public string $directory,
        public ArtifactManifest $manifest,
    ) {
    }

    public function snapshotPath(): string
    {
        return $this->directory.'/'.$this->manifest->snapshot->name;
    }

    public function databasePath(): string
    {
        return $this->directory.'/'.$this->manifest->database->name;
    }

    public function manifestPath(): string
    {
        return $this->directory.'/'.self::MANIFEST;
    }
}
