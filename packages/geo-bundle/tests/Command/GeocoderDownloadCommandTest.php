<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\FakeDumpDownloader;
use Tito10047\Geocoder\Import\Download\Exception\DownloadException;

final class GeocoderDownloadCommandTest extends TestCase
{
    private const COMMAND = 'tito10047-geo:geocoder:download';
    private const DUMPS = '/var/geo/dumps';

    private ?TestApplication $application = null;

    protected function tearDown(): void
    {
        $this->application?->shutdown();
    }

    public function testDownloadsTheConfiguredRegions(): void
    {
        $downloader = $this->downloader(['regions' => ['europe/slovakia', 'europe/czech-republic']]);
        $downloader->unchanged = ['europe/czech-republic'];
        $tester = $this->tester();

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame([['europe/slovakia', self::DUMPS, false], ['europe/czech-republic', self::DUMPS, false]], $downloader->downloads);
        $display = $tester->getDisplay(true);
        $this->assertStringContainsString('europe/slovakia: downloaded /var/geo/dumps/slovakia.jsonl.zst (2 KiB, changed 2026-10-17 23:30 UTC)', $display);
        $this->assertStringContainsString('europe/czech-republic: unchanged, /var/geo/dumps/czech-republic.jsonl.zst', $display);
        $this->assertStringContainsString(Attribution::OPENSTREETMAP, $display);
        $this->assertStringContainsString('tito10047-geo:geocoder:import', $display);
    }

    public function testDownloadsTheGivenRegionsByForce(): void
    {
        $downloader = $this->downloader(['regions' => ['europe/slovakia']]);
        $tester = $this->tester();

        $tester->execute(['regions' => ['europe/austria'], '--force' => true], ['decorated' => true]);

        $tester->assertCommandIsSuccessful();
        $this->assertSame([['europe/austria', self::DUMPS, true]], $downloader->downloads);
        // the progress bar of a terminal
        $this->assertStringContainsString('europe/austria: 2 KiB of 2 KiB', $tester->getDisplay(true));
    }

    public function testGoesOnAfterAFailedDownload(): void
    {
        $downloader = $this->downloader(['regions' => ['europe/slovakia', 'europe/austria']]);
        $downloader->failures['europe/slovakia'] = new DownloadException('The server answered 404 Not Found to the download of https://example.com/x.');
        $tester = $this->tester();

        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertCount(2, $downloader->downloads);
        $display = $tester->getDisplay(true);
        $this->assertStringContainsString('404 Not Found', $display);
        $this->assertStringContainsString('europe/austria: downloaded', $display);
        $this->assertStringContainsString('1 of 2 downloads failed.', $display);
    }

    public function testNeedsRegions(): void
    {
        $downloader = $this->downloader();
        $tester = $this->tester();

        $tester->execute([]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('tito10047_geo.geocoder.import.regions', $tester->getDisplay(true));
        $this->assertSame([], $downloader->downloads);
    }

    public function testNeedsTheDirectoryOfTheDumps(): void
    {
        $this->application = new TestApplication(Configs::geocoder(['import' => ['regions' => ['europe/slovakia']]]));
        $tester = $this->tester();

        $tester->execute([]);

        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('tito10047_geo.geocoder.import.dumps_dir', $tester->getDisplay(true));
    }

    /**
     * @param array<string, mixed> $import
     */
    private function downloader(array $import = []): FakeDumpDownloader
    {
        $this->application = new TestApplication(Configs::geocoder(['import' => ['dumps_dir' => self::DUMPS] + $import]));

        return $this->application->service('tito10047_geo.geocoder.import.dump_downloader', FakeDumpDownloader::class);
    }

    private function tester(): CommandTester
    {
        $this->assertInstanceOf(TestApplication::class, $this->application);

        return $this->application->command(self::COMMAND);
    }
}
