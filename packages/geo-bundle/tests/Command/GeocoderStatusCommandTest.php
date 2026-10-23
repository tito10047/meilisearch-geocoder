<?php

namespace Tito10047\GeoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Tito10047\GeoBundle\Attribution;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestApplication;
use Tito10047\GeoBundle\Tests\Fake\FakeIndexManager;
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
        $this->indexes = $this->application->service('tito10047_geo.geocoder.index_manager', FakeIndexManager::class);
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
        $tester = $this->application->command('tito10047-geo:geocoder:status');

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        $display = $tester->getDisplay();
        $this->assertSame(['places'], $this->indexes->statusCalls);
        $this->assertStringContainsString('4 420 000', $display);
        $this->assertStringContainsString('1.35.1', $display);
        $this->assertStringContainsString($settings, $display);
        $this->assertStringContainsString(Attribution::OPENSTREETMAP, $display);
    }

    public function testMissingIndexFails(): void
    {
        $this->indexes->exception = new MeilisearchException('Index `places` not found.');
        $tester = $this->application->command('tito10047-geo:geocoder:status');

        $tester->execute([]);

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('Index `places` not found.', $tester->getDisplay());
    }
}
