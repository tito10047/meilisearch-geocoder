<?php

namespace Tito10047\GeoBundle\Tests\Artifact;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Tito10047\GeoBundle\Artifact\ArtifactInstallerFactory;
use Tito10047\GeoBundle\Exception\UnknownMeilisearchVersionException;
use Tito10047\GeoBundle\Tests\Fake\Builds;
use Tito10047\GeoBundle\Tests\Fake\FakeArtifactBuilder;
use Tito10047\GeoBundle\Tests\Fake\FakeIndexManager;
use Tito10047\Geocoder\Artifact\ArtifactInstallerInterface;
use Tito10047\Geocoder\Artifact\Exception\ArtifactException;
use Tito10047\Geocoder\Index\Exception\MeilisearchException;
use Tito10047\Geocoder\Index\IndexStatus;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Storage\TableNames;

final class ArtifactInstallerFactoryTest extends TestCase
{
    private string $artifact;

    protected function setUp(): void
    {
        // a manifest of Meilisearch 1.35.1: an installer of another version rejects it, naming its own
        $this->artifact = sys_get_temp_dir().'/geo-bundle-artifact-'.bin2hex(random_bytes(6));
        mkdir($this->artifact);
        file_put_contents($this->artifact.'/manifest.json', FakeArtifactBuilder::manifest(Builds::build())->toJson());
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->artifact);
    }

    /**
     * @return iterable<string, array{string|null, string|null, string}>
     */
    public static function versions(): iterable
    {
        yield 'given' => ['9.0.0', '8.0.0', '9.0.0'];
        yield 'configured' => [null, '8.0.0', '8.0.0'];
        yield 'of the running instance' => [null, null, '7.0.0'];
    }

    #[DataProvider('versions')]
    public function testCreatesTheInstallerOfTheVersion(?string $given, ?string $configured, string $expected): void
    {
        $indexes = new FakeIndexManager();
        $indexes->indexUids = ['movies', 'geo'];
        $indexes->status = new IndexStatus('geo', 10, 1, 1, false, null, '7.0.0');
        $factory = new ArtifactInstallerFactory(new TimestampIndexNaming(), new TableNames(), $configured, $indexes);

        $this->assertSame($expected, $this->versionOf($factory->create($given)));
        $this->assertSame(null === $given && null === $configured ? ['geo'] : [], $indexes->statusCalls, 'The live index tells the version, only when needed.');
    }

    public function testReadsTheVersionFromAnyIndexWithoutTheLiveOne(): void
    {
        $indexes = new FakeIndexManager();
        $indexes->indexUids = ['movies'];
        $indexes->status = new IndexStatus('movies', 10, 1, 1, false, null, '7.0.0');

        $this->assertSame('7.0.0', $this->versionOf((new ArtifactInstallerFactory(new TimestampIndexNaming(), new TableNames(), null, $indexes))->create()));
        $this->assertSame(['movies'], $indexes->statusCalls);
    }

    /**
     * @return iterable<string, array{FakeIndexManager|null, string}>
     */
    public static function unknownVersions(): iterable
    {
        yield 'no instance' => [null, 'The Meilisearch version is unknown: set'];

        $empty = new FakeIndexManager();
        yield 'empty instance' => [$empty, 'has no index to read it from'];

        $down = new FakeIndexManager();
        $down->exception = new MeilisearchException('Connection refused.');
        yield 'unreachable instance' => [$down, 'cannot be read from the running instance (Connection refused.)'];
    }

    #[DataProvider('unknownVersions')]
    public function testFailsWithoutAVersion(?FakeIndexManager $indexes, string $message): void
    {
        $this->expectException(UnknownMeilisearchVersionException::class);
        $this->expectExceptionMessage($message);

        (new ArtifactInstallerFactory(new TimestampIndexNaming(), new TableNames(), null, $indexes))->create();
    }

    private function versionOf(ArtifactInstallerInterface $installer): string
    {
        try {
            $installer->install($this->artifact, $this->artifact, new \PDO('sqlite::memory:'));
        } catch (ArtifactException $exception) {
            if (1 === preg_match('/the instance is ([^,]+),/', $exception->getMessage(), $match)) {
                return $match[1];
            }

            throw $exception;
        }

        $this->fail('The installer accepted an artifact of another version.');
    }
}
