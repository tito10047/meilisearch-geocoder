<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\Builds;
use Tito10047\GeoBundle\Tests\Fake\FakeImporter;
use Tito10047\GeoBundle\Tests\Fake\FakeIndexManager;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Index\Exception\MeilisearchException;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\IndexStatus;

final class GeocoderStatusCommandTest extends TestCase
{
    private TestApplication $application;
    private FakeIndexManager $indexes;

    protected function setUp(): void
    {
        $this->application = new TestApplication(Configs::geocoder(['meilisearch' => ['url' => 'http://127.0.0.1:7700', 'index' => 'places']]));
        $this->indexes = $this->application->service('geo.geocoder.index_manager', FakeIndexManager::class);
    }

    protected function tearDown(): void
    {
        $this->application->shutdown();
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function settings(): iterable
    {
        yield 'default settings' => [(new IndexSettings())->hash(), 'the default settings'];
        yield 'custom settings' => [(new IndexSettings(maxTotalHits: 50))->hash(), 'custom settings ('.substr((new IndexSettings(maxTotalHits: 50))->hash(), 0, 12).')'];
        yield 'foreign settings' => [null, 'not the settings of this library'];
    }

    #[DataProvider('settings')]
    public function testPrintsTheStatusOfTheLiveIndex(?string $settingsHash, string $settings): void
    {
        $this->indexes->status = new IndexStatus('places', 4420000, 3_000_000_000, 4_000_000_000, false, $settingsHash, '1.35.1');
        $tester = $this->application->command('geo:status');

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        $this->assertSame(['places'], $this->indexes->statusCalls);
        $this->assertStringContainsString('4 420 000', $display);
        $this->assertStringContainsString('1.35.1', $display);
        $this->assertStringContainsString($settings, $display);
        $this->assertStringContainsString(Attribution::OPENSTREETMAP, $display);
    }

    public function testPrintsTheLiveBuild(): void
    {
        $this->indexes->status = new IndexStatus('places', 212000, 3_000_000_000, 4_000_000_000, false, (new IndexSettings())->hash(), '1.35.1');
        $this->application->service('geo.geocoder.importer', FakeImporter::class)->live = Builds::build();
        $tester = $this->application->command('geo:status');

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay(true);
        $this->assertStringContainsString('Live build', $display);
        $this->assertStringContainsString(Builds::UID, $display);
        $this->assertStringContainsString('CZ 2026-10-16, SK 2026-10-17', $display);
        $this->assertStringContainsString('4 234 567', $display);
        $this->assertStringContainsString('Schema version', $display);
    }

    public function testSaysWhenNoBuildWasPublished(): void
    {
        $this->indexes->status = new IndexStatus('places', 212000, 3_000_000_000, 4_000_000_000, false, null, '1.35.1');
        $tester = $this->application->command('geo:status');

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('No build was published yet', $tester->getDisplay(true));
    }

    public function testWarnsWhenTheLiveBuildCannotBeRead(): void
    {
        $this->indexes->status = new IndexStatus('places', 212000, 3_000_000_000, 4_000_000_000, false, null, '1.35.1');
        $this->application->service('geo.geocoder.importer', FakeImporter::class)->liveException = new GeocoderUnavailableException('The database of the geocoder cannot be connected to: unable to open database file');
        $tester = $this->application->command('geo:status');

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('The live build cannot be read: The database of the geocoder cannot be connected to', (string) preg_replace('/\s+/', ' ', $tester->getDisplay(true)));
    }

    public function testUnreachableMeilisearchFails(): void
    {
        $this->indexes->exception = new MeilisearchException('Index `places` not found.');
        $tester = $this->application->command('geo:status');

        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Index `places` not found.', (string) preg_replace('/\s+/', ' ', $tester->getDisplay(true)));
    }

    public function testMissingLiveIndexFails(): void
    {
        $tester = $this->application->command('geo:status');

        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('The live index places does not exist in Meilisearch 1.35.1', (string) preg_replace('/\s+/', ' ', $tester->getDisplay(true)));
    }

    public function testPrintsTheLiveBuildWhileMeilisearchIsDown(): void
    {
        $this->indexes->exception = new MeilisearchException('Cannot read the index places from Meilisearch: Connection refused');
        $this->application->service('geo.geocoder.importer', FakeImporter::class)->live = Builds::build();
        $tester = $this->application->command('geo:status');

        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay(true));
        $this->assertStringContainsString('Meilisearch cannot be asked for the live index places: Cannot read the index places from Meilisearch: Connection refused', $display);
        $this->assertStringContainsString('Live build', $display);
        $this->assertStringContainsString(Builds::UID, $display);
    }

    public function testNotConfiguredMeilisearchFails(): void
    {
        $application = new TestApplication(Configs::geocoder(['meilisearch' => ['url' => Configs::EMPTY_ENV]]), []);
        try {
            $tester = $application->command('geo:status');

            $tester->execute([]);

            $this->assertSame(Command::FAILURE, $tester->getStatusCode());
            $this->assertStringContainsString('Meilisearch is not configured: geo.geocoder.meilisearch.url is empty.', $tester->getDisplay());
        } finally {
            $application->shutdown();
        }
    }
}
