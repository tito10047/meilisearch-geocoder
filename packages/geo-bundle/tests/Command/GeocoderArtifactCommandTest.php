<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\Builds;
use Tito10047\GeoBundle\Tests\Fake\FakeArtifactBuilder;
use Tito10047\GeoBundle\Tests\Fake\FakeImporter;
use Tito10047\Geocoder\Artifact\Exception\ArtifactException;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;

final class GeocoderArtifactCommandTest extends TestCase
{
    private const COMMAND = 'geo:artifact';

    private ?TestApplication $application = null;

    protected function tearDown(): void
    {
        $this->application?->shutdown();
    }

    public function testBuildsAnArtifactOfTheLiveBuild(): void
    {
        $application = $this->application(Configs::geocoder(['meilisearch' => ['url' => 'http://127.0.0.1:7700', 'snapshot_dir' => '/srv/meili/snapshots']]));
        $application->service('geo.geocoder.importer', FakeImporter::class)->live = Builds::build();
        $tester = $application->command(self::COMMAND);

        $tester->execute(['directory' => '/srv/artifact']);

        $tester->assertCommandIsSuccessful();
        $this->assertSame([[Builds::UID, '/srv/artifact']], $application->service('geo.geocoder.artifact_builder', FakeArtifactBuilder::class)->artifacts);
        $display = $this->display($tester);
        $this->assertStringContainsString('1.35.1', $display);
        $this->assertStringContainsString('937.0 MiB', $display);
        $this->assertStringContainsString('geo:artifact:install', $display);
        $this->assertStringContainsString(Attribution::OPENSTREETMAP, $display);
    }

    public function testNeedsTheSnapshotDirectory(): void
    {
        $tester = $this->application(Configs::geocoder())->command(self::COMMAND);

        $tester->execute(['directory' => '/srv/artifact']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('configure "geo.geocoder.meilisearch.snapshot_dir"', $this->display($tester));
    }

    /**
     * @return iterable<string, array{bool, \Throwable}>
     */
    public static function failures(): iterable
    {
        yield 'nothing published' => [true, new BuildNotFoundException('There is no build in the tables geo_house.')];
        yield 'other indexes' => [false, new ArtifactException('The snapshot would hold the other indexes of the instance too (movies).')];
    }

    #[DataProvider('failures')]
    public function testFailureFails(bool $ofTheImporter, \Throwable $failure): void
    {
        $application = $this->application(Configs::geocoder(['meilisearch' => ['url' => 'http://127.0.0.1:7700', 'snapshot_dir' => '/srv/meili/snapshots']]));
        $importer = $application->service('geo.geocoder.importer', FakeImporter::class);
        $importer->live = Builds::build();
        if ($ofTheImporter) {
            $importer->liveException = $failure;
        } else {
            $application->service('geo.geocoder.artifact_builder', FakeArtifactBuilder::class)->exception = $failure;
        }
        $tester = $application->command(self::COMMAND);

        $tester->execute(['directory' => '/srv/artifact']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString($failure->getMessage(), $this->display($tester));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function application(array $config): TestApplication
    {
        return $this->application = new TestApplication($config);
    }

    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay(true));
    }
}
