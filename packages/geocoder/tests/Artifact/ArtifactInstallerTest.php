<?php

namespace Tito10047\Geocoder\Tests\Artifact;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Artifact\Artifact;
use Tito10047\Geocoder\Artifact\ArtifactInstaller;
use Tito10047\Geocoder\Artifact\Exception\ArtifactException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\Builds;
use Tito10047\Geocoder\Tests\Fake\PublishedBuild;
use Tito10047\Geocoder\Tests\Fake\Sql;
use Tito10047\Geocoder\Tests\Fake\TemporaryDirectory;

final class ArtifactInstallerTest extends TestCase
{
    private TemporaryDirectory $temporary;
    private PublishedBuild $published;
    private Artifact $artifact;
    private string $dataDirectory;
    private \PDO $target;

    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory();
        $this->published = new PublishedBuild($this->temporary->path);
        $this->artifact = $this->published->artifact();
        $this->dataDirectory = $this->temporary->path.'/meili_data';
        mkdir($this->dataDirectory);
        $this->target = new \PDO('sqlite::memory:');
    }

    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    public function testInstallsTheSnapshotAndTheTables(): void
    {
        $installed = (new ArtifactInstaller('1.35.1'))->install($this->artifact->directory, $this->dataDirectory, $this->target);

        $this->assertEquals($this->artifact->manifest, $installed->manifest);
        $this->assertSame($this->dataDirectory.'/geocoder.snapshot', $installed->snapshotPath);
        $this->assertFileEquals($this->artifact->snapshotPath(), $installed->snapshotPath);
        $this->assertSame(['geocoder.snapshot'], array_values(array_diff(scandir($this->dataDirectory) ?: [], ['.', '..'])));

        $storage = new PdoBuildStorage($this->target);
        $this->assertEquals($this->published->build, $installed->build);
        $this->assertEquals($this->published->build, $storage->read(new TableNames()));
        $this->assertSame([], $storage->suffixes(new TableNames()));
        $this->assertSame(134, (int) Sql::value($this->target, 'SELECT COUNT(*) FROM geo_house'));
    }

    public function testReplacesTheLiveTablesOfTheApplication(): void
    {
        Builds::store($this->target, new TableNames('app_house', 'app_name', 'app_meta'), 'geo_20200101000000');

        $installed = (new ArtifactInstaller('1.35.1', tables: new TableNames('app_house', 'app_name', 'app_meta')))->install($this->artifact->directory, $this->dataDirectory, $this->target);

        $this->assertSame('geo_20261023120000', (new PdoBuildStorage($this->target))->read(new TableNames('app_house', 'app_name', 'app_meta'))->indexUid);
        $this->assertSame('geo_20261023120000', $installed->build->indexUid);
        $this->assertSame(134, (int) Sql::value($this->target, 'SELECT COUNT(*) FROM app_house'));
    }

    public function testInstallsIntoAnSqliteFileByAttaching(): void
    {
        $target = new \PDO('sqlite:'.$this->temporary->path.'/target.sqlite');

        (new ArtifactInstaller('1.35.1'))->install($this->artifact->directory, $this->dataDirectory, $target);

        $this->assertSame(134, (int) Sql::value($target, 'SELECT COUNT(*) FROM geo_house'));
    }

    public function testInstallsAgainAfterAFailedInstallation(): void
    {
        // the tables of the build left by an installation that failed
        (new PdoBuildStorage($this->target))->create((new TableNames())->withSuffix('_20261023120000'));

        (new ArtifactInstaller('1.35.1'))->install($this->artifact->directory, $this->dataDirectory, $this->target);

        $this->assertSame(134, (int) Sql::value($this->target, 'SELECT COUNT(*) FROM geo_house'));
    }

    public function testRefusesAnotherVersionOfMeilisearch(): void
    {
        $this->assertRefused('of Meilisearch 1.35.1; the instance is 1.36.0', new ArtifactInstaller('1.36.0'));
    }

    public function testRefusesAnInstanceWithADatabase(): void
    {
        mkdir($this->dataDirectory.'/data.ms');

        $this->assertRefused('has a database (data.ms)', new ArtifactInstaller('1.35.1'));
    }

    public function testRefusesACorruptFile(): void
    {
        $snapshot = (string) file_get_contents($this->artifact->snapshotPath());
        file_put_contents($this->artifact->snapshotPath(), strrev($snapshot));

        $this->assertRefused('The file meilisearch.snapshot of the artifact is corrupt', new ArtifactInstaller('1.35.1'));
    }

    public function testRefusesAMissingFile(): void
    {
        unlink($this->artifact->databasePath());

        $this->assertRefused('The file geocoder.sqlite of the artifact is missing', new ArtifactInstaller('1.35.1'));
    }

    public function testRefusesAnotherSchemaVersion(): void
    {
        $this->editManifest(static fn (array $manifest): array => ['schema_version' => 1] + $manifest);

        $this->assertRefused('schema version 1', new ArtifactInstaller('1.35.1'));
    }

    public function testRefusesAnotherFormat(): void
    {
        $this->editManifest(static fn (array $manifest): array => ['format' => 2] + $manifest);

        $this->assertRefused('the format 2', new ArtifactInstaller('1.35.1'));
    }

    public function testRefusesAnIndexTheApplicationDoesNotSearch(): void
    {
        $this->assertRefused('The index of the artifact is geo, the application searches addresses', new ArtifactInstaller('1.35.1', new TimestampIndexNaming('addresses')));
    }

    public function testRefusesADirectoryWithoutManifest(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not an artifact');

        (new ArtifactInstaller('1.35.1'))->install($this->dataDirectory, $this->dataDirectory, $this->target);
    }

    public function testRefusesAMissingDataDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not exist');

        (new ArtifactInstaller('1.35.1'))->install($this->artifact->directory, $this->temporary->path.'/missing', $this->target);
    }

    public function testRejectsAnEmptyVersion(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ArtifactInstaller('');
    }

    /**
     * The installation is refused before anything changes.
     */
    private function assertRefused(string $message, ArtifactInstaller $installer): void
    {
        try {
            $installer->install($this->artifact->directory, $this->dataDirectory, $this->target);
            $this->fail('The artifact was installed.');
        } catch (ArtifactException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertSame([], array_values(array_diff(scandir($this->dataDirectory) ?: [], ['.', '..', 'data.ms'])));
        $this->assertFalse((new PdoBuildStorage($this->target))->exists(new TableNames()));
    }

    /**
     * @param callable(array<string, mixed>): array<string, mixed> $edit
     */
    private function editManifest(callable $edit): void
    {
        $manifest = json_decode((string) file_get_contents($this->artifact->manifestPath()), true, 8, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($manifest);
        /* @var array<string, mixed> $manifest */
        file_put_contents($this->artifact->manifestPath(), json_encode($edit($manifest), \JSON_THROW_ON_ERROR));
    }
}
