<?php

namespace Tito10047\Geocoder\Tests\Status;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\GeocoderNotConfiguredException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Status\GeocoderState;
use Tito10047\Geocoder\Status\GeocoderStatusProvider;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\Builds;
use Tito10047\Geocoder\Tests\Fake\FakeImporter;
use Tito10047\Geocoder\Tests\Index\Fixtures\InMemoryIndexManager;

final class GeocoderStatusProviderTest extends TestCase
{
    public function testReadyWithTheLiveIndexAndBuild(): void
    {
        $importer = new FakeImporter();
        $importer->live = Builds::build(new TableNames());

        $status = (new GeocoderStatusProvider(new InMemoryIndexManager(['geo' => 'geo_20261023120000']), 'geo', $importer))->status();

        $this->assertSame(GeocoderState::Ready, $status->state);
        $this->assertSame('geo', $status->indexUid);
        $this->assertSame('geo', $status->index?->uid);
        $this->assertSame('1.35.1', $status->meilisearchVersion);
        $this->assertSame($importer->live, $status->liveBuild);
        $this->assertNull($status->error);
        $this->assertNull($status->buildError);
    }

    public function testNoIndexYetStillTellsTheVersion(): void
    {
        $status = (new GeocoderStatusProvider(new InMemoryIndexManager(['products' => 'products']), 'geo', new FakeImporter()))->status();

        $this->assertSame(GeocoderState::NoIndex, $status->state);
        $this->assertSame('1.35.1', $status->meilisearchVersion);
        $this->assertNull($status->index);
        $this->assertNull($status->liveBuild);
        $this->assertNull($status->buildError);
    }

    public function testAnOutageIsAStatusNotAnException(): void
    {
        $indexes = new InMemoryIndexManager(['geo' => 'geo']);
        $indexes->failOn = 'exists';
        $importer = new FakeImporter();
        $importer->live = Builds::build(new TableNames());

        $status = (new GeocoderStatusProvider($indexes, 'geo', $importer))->status();

        $this->assertSame(GeocoderState::Unreachable, $status->state);
        $this->assertSame('The exists failed.', $status->error);
        $this->assertNull($status->meilisearchVersion);
        // the database answers while the search engine does not
        $this->assertSame($importer->live, $status->liveBuild);
    }

    public function testAGeocoderSwitchedOffIsNotConfigured(): void
    {
        $indexes = new InMemoryIndexManager();
        $indexes->failOn = 'exists';
        $indexes->failure = new GeocoderNotConfiguredException('Meilisearch is not configured.');

        $status = (new GeocoderStatusProvider($indexes, 'geo'))->status();

        $this->assertSame(GeocoderState::NotConfigured, $status->state);
        $this->assertSame('Meilisearch is not configured.', $status->error);
    }

    public function testAFailingIndexStatusIsAnOutage(): void
    {
        $indexes = new InMemoryIndexManager(['geo' => 'geo']);
        $indexes->failOn = 'status';

        $status = (new GeocoderStatusProvider($indexes, 'geo'))->status();

        $this->assertSame(GeocoderState::Unreachable, $status->state);
        $this->assertSame('The status failed.', $status->error);
    }

    public function testTellsWhyTheLiveBuildCannotBeRead(): void
    {
        $importer = new FakeImporter();
        $importer->liveException = new StorageException('The database went away.');

        $status = (new GeocoderStatusProvider(new InMemoryIndexManager(['geo' => 'geo']), 'geo', $importer))->status();

        $this->assertSame(GeocoderState::Ready, $status->state);
        $this->assertNull($status->liveBuild);
        $this->assertSame('The database went away.', $status->buildError);
    }

    public function testReadsNoBuildWithoutAnImporter(): void
    {
        $status = (new GeocoderStatusProvider(new InMemoryIndexManager(['geo' => 'geo']), 'geo'))->status();

        $this->assertNull($status->liveBuild);
        $this->assertNull($status->buildError);
    }

    public function testRejectsAnEmptyUid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GeocoderStatusProvider(new InMemoryIndexManager(), '');
    }
}
