<?php

namespace Tito10047\GeoBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Tito10047\GeoBundle\Tests\App\TestKernel;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Search\GeocoderInterface;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Suggestion;

/**
 * Searches an index of the Meilisearch at MEILISEARCH_URL (with the key MEILISEARCH_KEY) through
 * the services the bundle builds; skipped when the variable is not set. Uses an index of a random
 * name, deleted afterwards.
 */
final class MeilisearchIntegrationTest extends KernelTestCase
{
    private string $index;

    protected function setUp(): void
    {
        $url = getenv('MEILISEARCH_URL');
        if (!\is_string($url) || '' === $url) {
            $this->markTestSkipped('Set MEILISEARCH_URL (and MEILISEARCH_KEY) to a Meilisearch ≥ 1.18 to run the integration tests.');
        }
        $key = getenv('MEILISEARCH_KEY');

        $this->index = 'geo_bundle_test_'.bin2hex(random_bytes(4));
        self::bootKernel(['extension_configs' => ['geo' => ['geocoder' => [
            'meilisearch' => ['url' => $url, 'api_key' => \is_string($key) && '' !== $key ? $key : null, 'index' => $this->index],
            'database' => ['dsn' => 'sqlite::memory:'],
        ]]]]);
    }

    protected function tearDown(): void
    {
        if (isset($this->index)) {
            $indexes = $this->indexManager();
            if ($indexes->exists($this->index)) {
                $indexes->delete($this->index);
            }
        }
        parent::tearDown();
    }

    public function testSearchesAndReportsTheLiveIndex(): void
    {
        $indexes = $this->indexManager();
        $indexes->create($this->index, new IndexSettings());
        $writer = $indexes->documentWriter($this->index);
        $writer->write(new Document('R1', DocumentKind::Place, 'city', 'Bratislava', ['Bratislava', 'Pressburg'], ['Bratislava'], ['de' => 'Pressburg'], 'Bratislava', 'Bratislavský kraj', 'SK', null, new Coordinates(48.1485965, 17.1077477), 90800));
        $writer->write(new Document('R2', DocumentKind::Place, 'village', 'Bratislavka', ['Bratislavka'], ['Bratislavka'], [], 'Bratislavka', '', 'CZ', null, new Coordinates(49.5, 15.5), 50100));
        $writer->close();

        $geocoder = self::getContainer()->get(GeocoderInterface::class);
        $this->assertInstanceOf(GeocoderInterface::class, $geocoder);
        $suggestions = $geocoder->suggest('pressb', new SearchOptions(limit: 3));
        $this->assertSame(['R1'], array_map(static fn (Suggestion $suggestion): string => $suggestion->id, $suggestions));

        $kernel = self::$kernel;
        $this->assertInstanceOf(KernelInterface::class, $kernel);
        $tester = new CommandTester((new Application($kernel))->find('geo:status'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('the default settings', $tester->getDisplay());
    }

    /**
     * Supports the "extension_configs" option, see TestKernel::__construct().
     *
     * @param array<mixed> $options
     */
    protected static function createKernel(array $options = []): TestKernel
    {
        return new TestKernel(extensionConfigs: $options['extension_configs'] ?? []);
    }

    private function indexManager(): IndexManagerInterface
    {
        $indexes = self::getContainer()->get(IndexManagerInterface::class);
        $this->assertInstanceOf(IndexManagerInterface::class, $indexes);

        return $indexes;
    }
}
