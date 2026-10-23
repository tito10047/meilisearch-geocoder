<?php

namespace Tito10047\GeoBundle\Tests\Fake;

use Tito10047\GeoBundle\Artifact\ArtifactInstallerFactoryInterface;
use Tito10047\Geocoder\Artifact\ArtifactInstallerInterface;
use Tito10047\Geocoder\Artifact\InstalledArtifact;

/**
 * Records the versions asked and creates installers recording the installations, installing nothing.
 */
final class FakeArtifactInstallerFactory implements ArtifactInstallerFactoryInterface, ArtifactInstallerInterface
{
    /** Thrown by create(), e.g. an unknown version. */
    public ?\Throwable $createException = null;

    /** Thrown by install(). */
    public ?\Throwable $installException = null;

    /** @var list<string|null> */
    public array $versions = [];

    /** @var list<array{string, string}> artifact, data directory */
    public array $installations = [];

    public function create(?string $meilisearchVersion = null): ArtifactInstallerInterface
    {
        $this->versions[] = $meilisearchVersion;
        if ($this->createException instanceof \Throwable) {
            throw $this->createException;
        }

        return $this;
    }

    public function install(string $artifactDirectory, string $meilisearchDataDirectory, \PDO $database): InstalledArtifact
    {
        $this->installations[] = [$artifactDirectory, $meilisearchDataDirectory];
        if ($this->installException instanceof \Throwable) {
            throw $this->installException;
        }
        $build = Builds::build();

        return new InstalledArtifact(FakeArtifactBuilder::manifest($build), $meilisearchDataDirectory.'/geocoder.snapshot', $build);
    }
}
