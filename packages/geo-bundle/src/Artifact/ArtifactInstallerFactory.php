<?php

namespace Tito10047\GeoBundle\Artifact;

use Tito10047\GeoBundle\Exception\UnknownMeilisearchVersionException;
use Tito10047\Geocoder\Artifact\ArtifactInstaller;
use Tito10047\Geocoder\Artifact\ArtifactInstallerInterface;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexNamingInterface;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Creates ArtifactInstaller for the given version, else the configured one, else the version of
 * the running instance.
 *
 * Installing into a new data directory while the old instance still serves the live index (the
 * restart comes after) is the usual case: its version is the one the snapshot must have, unless
 * Meilisearch is upgraded at the same time — then the version is given.
 */
final readonly class ArtifactInstallerFactory implements ArtifactInstallerFactoryInterface
{
    /**
     * @param TableNames                 $tables             the live tables
     * @param string|null                $meilisearchVersion the configured version (tito10047_geo.geocoder.meilisearch.version)
     * @param IndexManagerInterface|null $indexes            the running instance, null when the bundle has no URL of it
     */
    public function __construct(
        private IndexNamingInterface $naming,
        private TableNames $tables,
        private ?string $meilisearchVersion = null,
        private ?IndexManagerInterface $indexes = null,
    ) {
    }

    public function create(?string $meilisearchVersion = null): ArtifactInstallerInterface
    {
        $version = $meilisearchVersion ?? $this->meilisearchVersion ?? $this->runningVersion();

        return new ArtifactInstaller($version, $this->naming, $this->tables);
    }

    /**
     * @throws UnknownMeilisearchVersionException when the instance cannot tell its version
     */
    private function runningVersion(): string
    {
        $hint = 'set "tito10047_geo.geocoder.meilisearch.version" or give the version of the instance the artifact is for';
        if (!$this->indexes instanceof IndexManagerInterface) {
            throw new UnknownMeilisearchVersionException(\sprintf('The Meilisearch version is unknown: %s.', $hint));
        }

        try {
            // only an index tells the version: the live one, else any
            $uids = $this->indexes->indexUids();
            $liveUid = $this->naming->liveUid();
            $uid = \in_array($liveUid, $uids, true) ? $liveUid : ($uids[0] ?? null);
            if (null === $uid) {
                throw new UnknownMeilisearchVersionException(\sprintf('The Meilisearch version is unknown: the running instance has no index to read it from; %s.', $hint));
            }

            return $this->indexes->status($uid)->meilisearchVersion;
        } catch (ExceptionInterface $exception) {
            throw new UnknownMeilisearchVersionException(\sprintf('The Meilisearch version cannot be read from the running instance (%s): %s.', $exception->getMessage(), $hint), 0, $exception);
        }
    }
}
