<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\Geocoder\Artifact\Artifact;
use Tito10047\Geocoder\Artifact\ArtifactBuilderInterface;
use Tito10047\Geocoder\Artifact\ArtifactFile;
use Tito10047\Geocoder\Artifact\ArtifactManifest;
use Tito10047\Geocoder\Import\Build;

/**
 * Records the artifacts asked and answers their manifest, writing nothing.
 */
final class FakeArtifactBuilder implements ArtifactBuilderInterface
{
    public ?\Throwable $exception = null;

    /** @var list<array{string, string}> build uid, directory */
    public array $artifacts = [];

    public function build(Build $build, string $directory): Artifact
    {
        $this->artifacts[] = [$build->indexUid, $directory];
        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }

        return new Artifact($directory, self::manifest($build));
    }

    public static function manifest(Build $build): ArtifactManifest
    {
        return new ArtifactManifest(
            meilisearchVersion: '1.35.1',
            settingsHash: $build->settingsHash,
            schemaVersion: $build->schemaVersion,
            indexUid: $build->indexUid,
            liveUid: 'geo',
            startedAt: $build->startedAt,
            createdAt: new \DateTimeImmutable('2026-10-23 13:00:00 UTC'),
            documents: $build->counts()->documents(),
            counts: $build->counts(),
            dataTimestamps: $build->dataTimestamps(),
            snapshot: new ArtifactFile('meilisearch.snapshot', 937 * 1024 * 1024, str_repeat('a', 64)),
            database: new ArtifactFile('geocoder.sqlite', 120 * 1024 * 1024, str_repeat('b', 64)),
        );
    }
}
