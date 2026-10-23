<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Exception\UnknownMeilisearchVersionException;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\Builds;
use Tito10047\GeoBundle\Tests\Fake\FakeArtifactInstallerFactory;
use Tito10047\Geocoder\Artifact\Exception\ArtifactException;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

final class GeocoderInstallArtifactCommandTest extends TestCase
{
    private const COMMAND = 'tito10047-geo:geocoder:install-artifact';

    private ?TestApplication $application = null;

    protected function tearDown(): void
    {
        $this->application?->shutdown();
    }

    public function testInstallsAndPrintsTheRestartInstruction(): void
    {
        $tester = $this->application()->command(self::COMMAND);

        $tester->execute(['artifact' => '/srv/artifact', 'data-directory' => '/srv/meili_data_new']);

        $tester->assertCommandIsSuccessful();
        $installers = $this->installers();
        $this->assertSame([null], $installers->versions);
        $this->assertSame([['/srv/artifact', '/srv/meili_data_new']], $installers->installations);
        $display = $this->display($tester);
        $this->assertStringContainsString('The tables of the build '.Builds::UID.' are live', $display);
        $this->assertStringContainsString('--import-snapshot /srv/meili_data_new/geocoder.snapshot', $display);
        $this->assertStringContainsString('--import-snapshot /meili_data/geocoder.snapshot', $display);
        $this->assertStringContainsString('Start Meilisearch 1.35.1', $display);
        $this->assertStringContainsString(Attribution::OPENSTREETMAP, $display);
    }

    public function testPassesTheVersionGiven(): void
    {
        $tester = $this->application()->command(self::COMMAND);

        $tester->execute(['artifact' => '/srv/artifact', 'data-directory' => '/srv/meili_data_new', '--meilisearch-version' => '1.36.0']);

        $tester->assertCommandIsSuccessful();
        $this->assertSame(['1.36.0'], $this->installers()->versions);
    }

    public function testRejectsAnEmptyVersion(): void
    {
        $tester = $this->application()->command(self::COMMAND);

        $tester->execute(['artifact' => '/srv/artifact', 'data-directory' => '/srv/meili_data_new', '--meilisearch-version' => '']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertSame([], $this->installers()->versions);
    }

    public function testIsAvailableWithoutTheUrlOfMeilisearch(): void
    {
        $tester = $this->application(Configs::geocoder(['meilisearch' => ['client' => 'app.meilisearch']]))->command(self::COMMAND);

        $tester->execute(['artifact' => '/srv/artifact', 'data-directory' => '/srv/meili_data_new']);

        $tester->assertCommandIsSuccessful();
    }

    /**
     * @return iterable<string, array{string, \Throwable}>
     */
    public static function failures(): iterable
    {
        yield 'unknown version' => ['createException', new UnknownMeilisearchVersionException('The Meilisearch version is unknown.')];
        yield 'not an artifact' => ['installException', new InvalidArgumentException('"/srv/artifact" is not an artifact: it has no manifest.json.')];
        yield 'other version' => ['installException', new ArtifactException('The snapshot of the artifact is of Meilisearch 1.35.1; the instance is 1.36.0.')];
        yield 'database' => ['installException', new GeocoderUnavailableException('The database of the geocoder cannot be connected to: unable to open database file')];
    }

    #[DataProvider('failures')]
    public function testFailureFails(string $property, \Throwable $failure): void
    {
        $this->installers()->{$property} = $failure;
        $tester = $this->application()->command(self::COMMAND);

        $tester->execute(['artifact' => '/srv/artifact', 'data-directory' => '/srv/meili_data_new']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString($failure->getMessage(), $this->display($tester));
    }

    /**
     * @param array<string, mixed>|null $config
     */
    private function application(?array $config = null): TestApplication
    {
        return $this->application ??= new TestApplication($config ?? Configs::geocoder());
    }

    private function installers(): FakeArtifactInstallerFactory
    {
        return $this->application()->service('tito10047_geo.geocoder.artifact_installer_factory', FakeArtifactInstallerFactory::class);
    }

    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay(true));
    }
}
