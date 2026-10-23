<?php

namespace Tito10047\Geocoder\Tests\Import;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\InvalidDumpException;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\ConverterInterface;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\Importer;
use Tito10047\Geocoder\Import\Photon\PhotonConverter;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Storage\BuildStorageInterface;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\Pdo\PdoSchema;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\FailingConverter;
use Tito10047\Geocoder\Tests\Fake\FailingPublishStorage;
use Tito10047\Geocoder\Tests\Fake\FrozenClock;
use Tito10047\Geocoder\Tests\Fake\RecordingProgress;
use Tito10047\Geocoder\Tests\Fake\Sql;
use Tito10047\Geocoder\Tests\Index\Fixtures\InMemoryIndexManager;

final class ImporterTest extends TestCase
{
    private const DUMP = __DIR__.'/../Fixtures/photon/slovakia-sample.jsonl';
    private const BUILD = 'geo_20261023120000';

    private InMemoryIndexManager $indexes;
    private \PDO $pdo;
    private PdoBuildStorage $storage;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $this->indexes = new InMemoryIndexManager();
        $this->pdo = new \PDO('sqlite::memory:');
        $this->storage = new PdoBuildStorage($this->pdo);
        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-10-23 12:00:00 UTC'));
    }

    public function testImportsIntoANewIndexAndNewTables(): void
    {
        $progress = new RecordingProgress();

        $build = $this->importer()->import([self::DUMP], $progress);

        $this->assertSame(self::BUILD, $build->indexUid);
        $this->assertEquals((new TableNames())->withSuffix('_20261023120000'), $build->tables);
        $this->assertEquals($this->clock->now(), $build->startedAt);
        $this->assertSame((new IndexSettings())->hash(), $build->settingsHash);
        $this->assertSame(PdoSchema::VERSION, $build->schemaVersion);
        $this->assertEquals(new ImportCounts(places: 4, streets: 13, pois: 4, houseNumbers: 134), $build->counts());
        $this->assertEquals(['SK' => new \DateTimeImmutable('2026-10-17T23:02:06+00:00')], $build->dataTimestamps());

        // the settings come with the creation, before the documents; the compaction last
        $this->assertSame(['create '.self::BUILD, 'writer '.self::BUILD, 'compact '.self::BUILD], $this->indexes->log);
        $this->assertTrue($this->indexes->writers[self::BUILD]->closed);
        $this->assertCount(21, $this->indexes->writers[self::BUILD]->documents);
        $this->assertSame('134', $this->value('SELECT COUNT(*) FROM geo_house_20261023120000'));
        $this->assertGreaterThan(0, (int) $this->value('SELECT COUNT(*) FROM geo_name_20261023120000'));
        $this->assertSame('3', $this->value("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND tbl_name = 'geo_house_20261023120000'"));
        $this->assertEquals($build, $this->storage->read($build->tables));
        $this->assertNotSame([], $progress->calls);

        // nothing live changed
        $this->assertFalse($this->indexes->exists('geo'));
        $this->assertFalse($this->storage->exists(new TableNames()));
    }

    public function testStartsTheBuildAtAWholeSecond(): void
    {
        $this->clock->now = new \DateTimeImmutable('2026-10-23 12:00:00.750 UTC');

        $build = $this->importer()->import([self::DUMP]);

        $this->assertEquals(new \DateTimeImmutable('2026-10-23 12:00:00 UTC'), $build->startedAt);
        $this->assertEquals($build, $this->storage->read($build->tables));
    }

    public function testImportsSeveralDumpsIntoOneBuild(): void
    {
        $build = $this->importer()->import((static function (): \Generator {
            yield self::DUMP;
            yield self::DUMP.'.zst';
        })());

        $this->assertCount(2, $build->dumps);
        $this->assertSame(self::DUMP.'.zst', $build->dumps[1]->dump);
        $this->assertSame(268, $build->counts()->houseNumbers);
        $this->assertSame('268', $this->value('SELECT COUNT(*) FROM geo_house_20261023120000'));
    }

    public function testPublishesTheFirstBuild(): void
    {
        $importer = $this->importer();
        $build = $importer->import([self::DUMP]);

        $live = $importer->publish($build);

        $this->assertEquals($build->withTables(new TableNames()), $live);
        $this->assertSame(['geo'], $this->indexes->indexUids());
        $this->assertSame(self::BUILD, $this->indexes->indexes['geo']);
        $this->assertEquals($live, $importer->live());
        $this->assertSame([], $this->storage->suffixes(new TableNames()));
    }

    public function testReplacesTheLiveBuildAndRemovesTheBuildsLeftBefore(): void
    {
        $importer = $this->importer();
        $first = $importer->import([self::DUMP]);
        $importer->publish($first);
        $this->clock->advance('+1 hour');
        $abandoned = $importer->import([self::DUMP]);
        $this->clock->advance('+1 hour');
        $published = $importer->import([self::DUMP]);
        $this->clock->advance('+1 hour');
        $running = $importer->import([self::DUMP]);

        $importer->publish($published);

        $this->assertSame($published->indexUid, $importer->live()->indexUid);
        $this->assertSame($published->indexUid, $this->indexes->indexes['geo']);
        // the replaced live index and the abandoned build are gone, a build started later may still be running
        $uids = $this->indexes->indexUids();
        sort($uids);
        $this->assertSame(['geo', $running->indexUid], $uids);
        $this->assertSame(['_20261023150000'], $this->storage->suffixes(new TableNames()));
        $this->assertNotContains($abandoned->indexUid, $uids);
    }

    public function testRemovesWhatItBuiltWhenTheImportFails(): void
    {
        $failure = new RuntimeException('The disk is full.');
        $importer = $this->importer(new FailingConverter(new PhotonConverter(), [self::DUMP.'.zst'], $failure));

        try {
            $importer->import([self::DUMP, self::DUMP.'.zst']);
            $this->fail('The import did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception);
        }

        $this->assertSame([], $this->indexes->indexUids());
        $this->assertSame('delete '.self::BUILD, end($this->indexes->log));
        $this->assertFalse($this->storage->exists((new TableNames())->withSuffix('_20261023120000')));
        $this->assertFalse($this->pdo->inTransaction());
    }

    public function testKeepsAnInvalidDumpAnInvalidArgument(): void
    {
        $this->expectException(InvalidDumpException::class);

        try {
            $this->importer()->import([__FILE__]);
        } finally {
            $this->assertSame([], $this->indexes->indexUids());
            $this->assertFalse($this->storage->exists((new TableNames())->withSuffix('_20261023120000')));
        }
    }

    public function testWrapsAFailureFromOutsideThePackage(): void
    {
        $failure = new \UnexpectedValueException('Something else.');
        $importer = $this->importer(new FailingConverter(new PhotonConverter(), [self::DUMP], $failure));

        try {
            $importer->import([self::DUMP]);
            $this->fail('The import did not fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception->getPrevious());
            $this->assertStringContainsString('The import of the build '.self::BUILD.' failed: Something else.', $exception->getMessage());
        }
        $this->assertSame([], $this->indexes->indexUids());
    }

    public function testRemovesWhatItBuiltWhenTheCompactionFails(): void
    {
        $this->indexes->failOn = 'compact';

        try {
            $this->importer()->import([self::DUMP]);
            $this->fail('The import did not fail.');
        } catch (RuntimeException) {
        }

        $this->assertSame([], $this->indexes->indexUids());
        $this->assertFalse($this->storage->exists((new TableNames())->withSuffix('_20261023120000')));
    }

    public function testRejectsAnImportWithoutDumps(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->importer()->import([]);
    }

    public function testRefusesASecondBuildInTheSameSecond(): void
    {
        $importer = $this->importer();
        $build = $importer->import([self::DUMP]);

        try {
            $importer->import([self::DUMP]);
            $this->fail('Two builds got the same name.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('exists already', $exception->getMessage());
        }

        $this->assertEquals($build, $importer->build(self::BUILD));
    }

    public function testFindsABuildNotPublishedYet(): void
    {
        $importer = $this->importer();
        $build = $importer->import([self::DUMP]);

        $this->assertEquals($build, $importer->build(self::BUILD));
    }

    public function testFindsNoBuildThatDoesNotExist(): void
    {
        $this->expectException(BuildNotFoundException::class);

        $this->importer()->build('geo_20200101000000');
    }

    public function testRejectsAnUidThatIsNotOfABuild(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->importer()->build('products');
    }

    public function testRefusesToPublishABuildTwice(): void
    {
        $importer = $this->importer();
        $build = $importer->import([self::DUMP]);
        $importer->publish($build);

        $this->expectException(BuildNotFoundException::class);

        $importer->publish($build);
    }

    public function testRefusesToPublishABuildWithoutItsTablesBeforeTouchingTheIndex(): void
    {
        $importer = $this->importer();
        $build = $importer->import([self::DUMP]);
        $this->storage->drop($build->tables);

        try {
            $importer->publish($build);
            $this->fail('A build without tables was published.');
        } catch (BuildNotFoundException) {
        }

        $this->assertSame([self::BUILD], $this->indexes->indexUids());
    }

    public function testRefusesToPublishABuildOfAnotherSchemaVersion(): void
    {
        $importer = $this->importer();
        $build = $importer->import([self::DUMP]);
        $this->pdo->exec(\sprintf("UPDATE %s SET meta_value = '1' WHERE meta_key = 'schema_version'", $build->tables->metadata));

        try {
            $importer->publish($build);
            $this->fail('A build of another schema version was published.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('schema version 1', $exception->getMessage());
        }

        $this->assertSame([self::BUILD], $this->indexes->indexUids());
    }

    public function testRefusesToPublishAnIndexOfAnotherNaming(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->importer()->publish(new Build('products', new TableNames(), $this->clock->now(), (new IndexSettings())->hash(), 1, [
            new \Tito10047\Geocoder\Import\DumpSummary('a', $this->clock->now(), ['SK'], new ImportCounts()),
        ]));
    }

    public function testHasNoLiveBuildBeforeAPublication(): void
    {
        $this->expectException(BuildNotFoundException::class);

        $this->importer()->live();
    }

    public function testUndoesTheSwapWhenTheTablesCannotBePublished(): void
    {
        $storage = new FailingPublishStorage($this->storage);
        $importer = $this->importer(storage: $storage);
        $first = $importer->import([self::DUMP]);
        $importer->publish($first);
        $this->clock->advance('+1 hour');
        $second = $importer->import([self::DUMP]);
        $storage->publishFailures = 1;

        try {
            $importer->publish($second);
            $this->fail('The publication did not fail.');
        } catch (StorageException) {
        }

        // the index and the tables stay of one build
        $this->assertSame(['geo' => $first->indexUid, $second->indexUid => $second->indexUid], $this->indexes->indexes);
        $this->assertSame($first->indexUid, $importer->live()->indexUid);

        $importer->publish($importer->build($second->indexUid));

        $this->assertSame(['geo' => $second->indexUid], $this->indexes->indexes);
        $this->assertSame($second->indexUid, $importer->live()->indexUid);
    }

    public function testUndoesTheRenameWhenTheTablesOfTheFirstBuildCannotBePublished(): void
    {
        $storage = new FailingPublishStorage($this->storage);
        $importer = $this->importer(storage: $storage);
        $build = $importer->import([self::DUMP]);
        $storage->publishFailures = 1;

        try {
            $importer->publish($build);
            $this->fail('The publication did not fail.');
        } catch (StorageException) {
        }

        $this->assertSame([self::BUILD => self::BUILD], $this->indexes->indexes);
        $this->assertSame(['swap '.self::BUILD.' geo', 'swap geo '.self::BUILD], \array_slice($this->indexes->log, -2));

        $importer->publish($importer->build(self::BUILD));

        $this->assertSame(['geo' => self::BUILD], $this->indexes->indexes);
        $this->assertSame(self::BUILD, $importer->live()->indexUid);
    }

    public function testFinishesAFirstPublicationWhoseRenameCouldNotBeUndone(): void
    {
        $storage = new FailingPublishStorage($this->storage);
        $importer = $this->importer(storage: $storage);
        $build = $importer->import([self::DUMP]);
        $storage->publishFailures = 1;
        $this->indexes->failOn = 'swap';
        $this->indexes->failFromCall = 2;

        try {
            $importer->publish($build);
            $this->fail('The publication did not fail.');
        } catch (StorageException) {
        }
        // the state left by the first integration: the index live, the tables not
        $this->assertSame(['geo' => self::BUILD], $this->indexes->indexes);
        $this->indexes->failOn = null;
        $log = \count($this->indexes->log);

        $live = $importer->publish($importer->build(self::BUILD));

        $this->assertSame(self::BUILD, $live->indexUid);
        $this->assertSame(self::BUILD, $importer->live()->indexUid);
        $this->assertSame(['geo' => self::BUILD], $this->indexes->indexes);
        $this->assertSame([], \array_slice($this->indexes->log, $log), 'The index was touched again.');
        $this->assertSame([], $this->storage->suffixes(new TableNames()));
    }

    public function testFinishesAPublicationWhoseSwapCouldNotBeUndone(): void
    {
        $storage = new FailingPublishStorage($this->storage);
        $importer = $this->importer(storage: $storage);
        $first = $importer->import([self::DUMP]);
        $importer->publish($first);
        $this->clock->advance('+1 hour');
        $second = $importer->import([self::DUMP]);
        $storage->publishFailures = 1;
        $this->indexes->failOn = 'swap';
        // the swap in succeeds, the swap back fails
        $this->indexes->failFromCall = 3;

        try {
            $importer->publish($second);
            $this->fail('The publication did not fail.');
        } catch (StorageException) {
        }
        $this->assertSame(['geo' => $second->indexUid, $second->indexUid => $first->indexUid], $this->indexes->indexes);
        $this->indexes->failOn = null;

        $importer->publish($importer->build($second->indexUid));

        $this->assertSame(['geo' => $second->indexUid], $this->indexes->indexes);
        $this->assertSame($second->indexUid, $importer->live()->indexUid);
        $this->assertSame('delete '.$second->indexUid, $this->indexes->log[\count($this->indexes->log) - 1]);
    }

    public function testFinishesAPublicationStoppedAfterTheTables(): void
    {
        $importer = $this->importer();
        $first = $importer->import([self::DUMP]);
        $importer->publish($first);
        $this->clock->advance('+1 hour');
        $second = $importer->import([self::DUMP]);
        $this->indexes->failOn = 'delete';

        try {
            $importer->publish($second);
            $this->fail('The publication did not fail.');
        } catch (RuntimeException) {
        }
        $this->indexes->failOn = null;
        // the tables of the build are live: it is published from there
        $this->assertSame($second->indexUid, $importer->live()->indexUid);

        $live = $importer->publish($importer->live());

        $this->assertSame($second->indexUid, $live->indexUid);
        $this->assertSame(['geo' => $second->indexUid], $this->indexes->indexes);
    }

    public function testPublishingTheLiveBuildAgainChangesNothing(): void
    {
        $importer = $this->importer();
        $build = $importer->import([self::DUMP]);
        $importer->publish($build);
        $log = \count($this->indexes->log);

        $live = $importer->publish($importer->live());

        $this->assertEquals($importer->live(), $live);
        $this->assertSame(['geo' => self::BUILD], $this->indexes->indexes);
        $this->assertSame([], \array_slice($this->indexes->log, $log));
    }

    public function testFindsNoBuildWhoseIndexWasRemovedDuringItsPublication(): void
    {
        $importer = $this->importer();
        $build = $importer->import([self::DUMP]);
        $this->storage->recordPublication($build->tables, self::BUILD);
        $this->indexes->delete(self::BUILD);

        $this->expectException(BuildNotFoundException::class);
        $this->expectExceptionMessage('exists neither under its uid nor as the live index geo');

        $importer->build(self::BUILD);
    }

    public function testCleansUpTheBuildsExceptThoseToKeep(): void
    {
        $importer = $this->importer();
        $first = $importer->import([self::DUMP]);
        $this->clock->advance('+1 second');
        $second = $importer->import([self::DUMP]);
        $this->indexes->indexes['products'] = 'products';
        // tables of a build whose index is gone already
        $this->indexes->delete($first->indexUid);

        $this->assertSame([$first->indexUid], $importer->cleanup([$second->indexUid]));

        $this->assertSame([$second->indexUid, 'products'], $this->indexes->indexUids());
        $this->assertSame(['_20261023120001'], $this->storage->suffixes(new TableNames()));
        $this->assertSame([$second->indexUid], $importer->cleanup());
        $this->assertSame([], $importer->cleanup());
    }

    private function importer(ConverterInterface $converter = new PhotonConverter(), ?BuildStorageInterface $storage = null): Importer
    {
        return new Importer($converter, $this->indexes, $storage ?? $this->storage, new TimestampIndexNaming(), new IndexSettings(), new TableNames(), $this->clock);
    }

    private function value(string $sql): string
    {
        return Sql::value($this->pdo, $sql);
    }
}
