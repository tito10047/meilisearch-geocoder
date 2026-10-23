<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\FakeImporter;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Index\Exception\MeilisearchException;

final class GeocoderPublishCommandTest extends TestCase
{
    private const COMMAND = 'tito10047-geo:geocoder:publish';

    private TestApplication $application;
    private FakeImporter $importer;

    protected function setUp(): void
    {
        $this->application = new TestApplication(Configs::geocoder());
        $this->importer = $this->application->service('tito10047_geo.geocoder.importer', FakeImporter::class);
    }

    protected function tearDown(): void
    {
        $this->application->shutdown();
    }

    public function testPublishesTheBuild(): void
    {
        $tester = $this->application->command(self::COMMAND);

        $tester->execute(['build' => 'geo_20261020080000']);

        $tester->assertCommandIsSuccessful();
        $this->assertSame(['geo_20261020080000'], $this->importer->builds);
        $this->assertSame(['geo_20261020080000'], $this->importer->published);
        $display = $tester->getDisplay(true);
        $this->assertStringContainsString('The build geo_20261020080000 is live.', $display);
        $this->assertStringContainsString(Attribution::OPENSTREETMAP, $display);
    }

    /**
     * @return iterable<string, array{string, \Throwable}>
     */
    public static function failures(): iterable
    {
        yield 'not a build' => ['buildException', new InvalidArgumentException('"places" is not the uid of a build of the index geo.')];
        yield 'missing build' => ['buildException', new BuildNotFoundException('The index geo_20261020080000 of the build does not exist.')];
        yield 'failed swap' => ['publishException', new MeilisearchException('The swap failed.')];
        yield 'database' => ['buildException', new GeocoderUnavailableException('The database of the geocoder cannot be connected to: unable to open database file')];
    }

    #[DataProvider('failures')]
    public function testFailureFails(string $property, \Throwable $failure): void
    {
        $this->importer->{$property} = $failure;
        $tester = $this->application->command(self::COMMAND);

        $tester->execute(['build' => 'geo_20261020080000']);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString($failure->getMessage(), (string) preg_replace('/\s+/', ' ', $tester->getDisplay(true)));
    }
}
