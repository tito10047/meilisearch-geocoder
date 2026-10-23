<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\FakeImporter;
use Tito10047\Geocoder\Exception\StorageException;

final class GeocoderCleanupCommandTest extends TestCase
{
    private const COMMAND = 'geo:cleanup';

    private TestApplication $application;
    private FakeImporter $importer;

    protected function setUp(): void
    {
        $this->application = new TestApplication(Configs::geocoder());
        $this->importer = $this->application->service('geo.geocoder.importer', FakeImporter::class);
    }

    protected function tearDown(): void
    {
        $this->application->shutdown();
    }

    public function testRemovesTheBuildsNotKept(): void
    {
        $this->importer->removed = ['geo_20261020080000', 'geo_20261021080000'];
        $tester = $this->application->command(self::COMMAND);

        $tester->execute(['--keep' => ['geo_20261023120000']]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame([['geo_20261023120000']], $this->importer->cleanups);
        $display = $tester->getDisplay(true);
        $this->assertStringContainsString('geo_20261020080000', $display);
        $this->assertStringContainsString('geo_20261021080000', $display);
        $this->assertStringContainsString('Removed 2 build(s).', $display);
    }

    public function testSaysWhenThereIsNothingToRemove(): void
    {
        $tester = $this->application->command(self::COMMAND);

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame([[]], $this->importer->cleanups);
        $this->assertStringContainsString('There is no build to remove.', $tester->getDisplay(true));
    }

    public function testFailureFails(): void
    {
        $this->importer->cleanupException = new StorageException('The table geo_house_20261020080000 cannot be dropped.');
        $tester = $this->application->command(self::COMMAND);

        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('cannot be dropped', $tester->getDisplay(true));
    }
}
