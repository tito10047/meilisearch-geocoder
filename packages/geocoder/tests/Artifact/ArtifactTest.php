<?php

namespace Tito10047\Geocoder\Tests\Artifact;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Artifact\Artifact;
use Tito10047\Geocoder\Artifact\ArtifactFile;
use Tito10047\Geocoder\Artifact\ArtifactManifest;
use Tito10047\Geocoder\Import\ImportCounts;

final class ArtifactTest extends TestCase
{
    public function testLocatesItsFiles(): void
    {
        $artifact = new Artifact('/srv/artifact', new ArtifactManifest(
            '1.35.1', hash('sha256', ''), 1, 'geo_20261023120000', 'geo', new \DateTimeImmutable(), new \DateTimeImmutable(), 0, new ImportCounts(), [],
            new ArtifactFile('s.snapshot', 1, hash('sha256', 's')),
            new ArtifactFile('d.sqlite', 1, hash('sha256', 'd')),
        ));

        $this->assertSame('/srv/artifact/s.snapshot', $artifact->snapshotPath());
        $this->assertSame('/srv/artifact/d.sqlite', $artifact->databasePath());
        $this->assertSame('/srv/artifact/manifest.json', $artifact->manifestPath());
    }
}
