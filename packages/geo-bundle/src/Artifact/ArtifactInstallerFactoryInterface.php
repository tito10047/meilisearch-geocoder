<?php

namespace Tito10047\GeoBundle\Artifact;

use Tito10047\GeoBundle\Exception\UnknownMeilisearchVersionException;
use Tito10047\Geocoder\Artifact\ArtifactInstallerInterface;

/**
 * Creates the installer of artifacts for the version of the target Meilisearch instance, which
 * is often known only when installing (the instance is being replaced).
 */
interface ArtifactInstallerFactoryInterface
{
    /**
     * @param string|null $meilisearchVersion the version of the instance the artifact is installed for ("1.35.1");
     *                                        the configured one, or the one of the running instance, when null
     *
     * @throws UnknownMeilisearchVersionException when no version is given, configured or readable from the instance
     */
    public function create(?string $meilisearchVersion = null): ArtifactInstallerInterface;
}
