<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Artifact\Artifact;
use Tito10047\Geocoder\Artifact\ArtifactBuilder;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\Importer;
use Tito10047\Geocoder\Import\Photon\PhotonConverter;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Index\Fixtures\InMemoryIndexManager;

/**
 * The sample dump imported and published into a fake index manager and an SQLite file, in a
 * temporary directory: the build instance of an artifact.
 */
final readonly class PublishedBuild
{
    public const DUMP = __DIR__.'/../Fixtures/photon/slovakia-sample.jsonl';

    public InMemoryIndexManager $indexes;
    public \PDO $pdo;
    public string $snapshotDirectory;
    public Importer $importer;
    public FrozenClock $clock;
    public Build $build;

    public function __construct(public string $directory, ?\PDO $pdo = null)
    {
        $this->snapshotDirectory = $directory.'/snapshots';
        mkdir($this->snapshotDirectory);
        $this->indexes = new InMemoryIndexManager();
        $this->indexes->snapshotDirectory = $this->snapshotDirectory;
        $this->pdo = $pdo ?? new \PDO('sqlite:'.$directory.'/geocoder.sqlite');
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-10-23 12:00:00 UTC'));
        $this->importer = new Importer(new PhotonConverter(), $this->indexes, new PdoBuildStorage($this->pdo), new TimestampIndexNaming(), new IndexSettings(), new TableNames(), $this->clock);
        $this->build = $this->importer->publish($this->importer->import([self::DUMP]));
    }

    public function artifactBuilder(): ArtifactBuilder
    {
        return new ArtifactBuilder($this->indexes, $this->pdo, $this->snapshotDirectory, clock: new FrozenClock(new \DateTimeImmutable('2026-10-23 14:00:00 UTC')));
    }

    public function artifact(): Artifact
    {
        return $this->artifactBuilder()->build($this->build, $this->directory.'/artifact');
    }
}
