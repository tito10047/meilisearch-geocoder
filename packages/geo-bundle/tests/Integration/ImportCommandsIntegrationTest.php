<?php

namespace Tito10047\GeoBundle\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Tito10047\GeoBundle\Tests\App\TestKernel;
use Tito10047\Geocoder\Index\IndexManagerInterface;

/**
 * Runs the commands of the import against the Meilisearch at MEILISEARCH_URL (with the key
 * MEILISEARCH_KEY): the committed Photon fixture is exported into files, imported from them,
 * published, inspected, searched and cleaned up. Skipped when the variable is not set. Uses an
 * index of a random name and an SQLite database in a temporary directory, removed afterwards.
 */
final class ImportCommandsIntegrationTest extends KernelTestCase
{
    private const DUMP = __DIR__.'/../../../geocoder/tests/Fixtures/photon/slovakia-sample.jsonl';

    private string $index;
    private string $directory;

    protected function setUp(): void
    {
        $url = getenv('MEILISEARCH_URL');
        if (!\is_string($url) || '' === $url) {
            $this->markTestSkipped('Set MEILISEARCH_URL (and MEILISEARCH_KEY) to a Meilisearch ≥ 1.18 to run the integration tests.');
        }
        $key = getenv('MEILISEARCH_KEY');

        $this->index = 'geo_bundle_import_'.bin2hex(random_bytes(4));
        $this->directory = sys_get_temp_dir().'/geo-bundle-import-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
        self::bootKernel(['extension_configs' => ['geo' => ['geocoder' => [
            'meilisearch' => ['url' => $url, 'api_key' => \is_string($key) && '' !== $key ? $key : null, 'index' => $this->index],
            'database' => ['dsn' => 'sqlite:'.$this->directory.'/geo.sqlite'],
        ]]]]);
    }

    protected function tearDown(): void
    {
        if (isset($this->index)) {
            $indexes = self::getContainer()->get(IndexManagerInterface::class);
            $this->assertInstanceOf(IndexManagerInterface::class, $indexes);
            foreach ($indexes->indexUids() as $uid) {
                if ($uid === $this->index || str_starts_with($uid, $this->index.'_')) {
                    $indexes->delete($uid);
                }
            }
            (new Filesystem())->remove($this->directory);
        }
        parent::tearDown();
    }

    public function testImportsPublishesAndSearchesTheFixture(): void
    {
        $export = $this->directory.'/export';
        $this->execute('import', ['dumps' => [self::DUMP], '--export' => $export]);
        $this->assertFileExists($export.'/documents.ndjson');

        $display = $this->execute('import', ['dumps' => [$export], '--no-publish' => true]);
        $this->assertSame(1, preg_match('/The build (\S+) is ready/', $display, $match), $display);
        $uid = $match[1];
        $this->assertStringStartsWith($this->index.'_', $uid);

        $display = $this->execute('publish', ['build' => $uid]);
        $this->assertStringContainsString('The build '.$uid.' is live.', $display);

        $display = $this->execute('status');
        $this->assertStringContainsString('Live build', $display);
        $this->assertStringContainsString($uid, $display);
        $this->assertStringContainsString('SK 2026-10-17', $display);
        $this->assertStringContainsString('the default settings', $display);

        $display = $this->execute('search', ['text' => 'zsolna', '--limit' => '1']);
        $this->assertStringContainsString('Žilina', $display);

        $this->assertStringContainsString('There is no build to remove.', $this->execute('cleanup'));
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return string the output, its blocks unwrapped
     */
    private function execute(string $command, array $input = []): string
    {
        $kernel = self::$kernel;
        $this->assertInstanceOf(KernelInterface::class, $kernel);
        $tester = new CommandTester((new Application($kernel))->find('geo:'.$command));
        $tester->execute($input);
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay(true));
        $this->assertSame(0, $tester->getStatusCode(), $display);

        return $display;
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
}
