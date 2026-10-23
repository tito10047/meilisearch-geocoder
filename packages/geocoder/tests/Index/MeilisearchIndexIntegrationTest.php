<?php

namespace Tito10047\Geocoder\Tests\Index;

use Meilisearch\Client;
use Meilisearch\Http\Client as MeilisearchHttp;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Index\Exception\TaskFailedException;
use Tito10047\Geocoder\Index\IndexingOptions;
use Tito10047\Geocoder\Index\IndexPublisher;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\MeilisearchDocumentWriter;
use Tito10047\Geocoder\Index\MeilisearchIndexManager;
use Tito10047\Geocoder\Index\TimestampIndexNaming;

/**
 * Builds, publishes and replaces an index in the Meilisearch at MEILISEARCH_URL (with the key
 * MEILISEARCH_KEY); skipped when the variable is not set. Uses indexes of a random prefix and
 * deletes them afterwards.
 */
#[Group('integration')]
final class MeilisearchIndexIntegrationTest extends TestCase
{
    private const FILLER_DOCUMENTS = 5000;

    /** @var non-empty-string */
    private string $url;
    private string $key;
    private MeilisearchIndexManager $manager;
    private TimestampIndexNaming $naming;
    private Client $client;

    protected function setUp(): void
    {
        $url = getenv('MEILISEARCH_URL');
        if (!\is_string($url) || '' === $url) {
            $this->markTestSkipped('Set MEILISEARCH_URL (and MEILISEARCH_KEY) to a Meilisearch ≥ 1.18 to run the integration tests.');
        }
        $key = getenv('MEILISEARCH_KEY');
        $this->url = $url;
        $this->key = \is_string($key) ? $key : '';

        $this->manager = new MeilisearchIndexManager(new MeilisearchHttp($this->url, $this->key), new IndexingOptions(batchSize: 1000, maxPendingTasks: 2, taskTimeout: 120.0, pollInterval: 0.05));
        $this->naming = new TimestampIndexNaming('it'.bin2hex(random_bytes(4)));
        $this->client = new Client($this->url, $this->key);
    }

    protected function tearDown(): void
    {
        if (!isset($this->manager)) {
            return;
        }
        foreach ($this->manager->indexUids() as $uid) {
            if (str_starts_with($uid, $this->naming->liveUid())) {
                $this->manager->delete($uid);
            }
        }
    }

    public function testBuildsPublishesAndReplacesAnIndexWithoutDowntime(): void
    {
        $start = new \DateTimeImmutable('2026-10-23 12:00:00 UTC');
        $live = $this->naming->liveUid();
        $publisher = new IndexPublisher($this->manager, $this->naming);

        // a first, small build becomes live by a rename
        $first = $this->naming->buildUid($start);
        $this->manager->create($first, new IndexSettings());
        $writer = $this->manager->documentWriter($first);
        $writer->write($this->place('R1', 'Bratislava', ['Bratislava', 'Pozsony', 'Pressburg'], 'SK', 48.14, 17.11, 90_900));
        $writer->close();
        $publisher->publish($first);
        $this->assertTrue($this->manager->exists($live));
        $this->assertFalse($this->manager->exists($first));
        $this->assertSame(1, $this->manager->status($live)->documents);

        // the second build is filled while the first one is live
        $second = $this->naming->buildUid($start->modify('+1 hour'));
        $this->manager->create($second, new IndexSettings());
        $writer = $this->manager->documentWriter($second);
        $count = 0;
        foreach ($this->documents() as $document) {
            $writer->write($document);
            ++$count;
        }
        $writer->close();
        $this->manager->waitForTasks($second);

        $status = $this->manager->status($second);
        $this->assertSame($count, $status->documents);
        $this->assertFalse($status->indexing);
        $this->assertSame((new IndexSettings())->hash(), $status->settingsHash);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+/', $status->meilisearchVersion);

        // the tuned ranking and the translations of variant D
        $this->assertSame('Košice', $this->firstHit($second, 'kosice')['name']);
        $this->assertSame('Wien', $this->firstHit($second, 'viedeň')['name']);
        $this->assertSame('Stephansplatz', $this->firstHit($second, 'stephansplatz vieden')['name']);
        $this->assertSame(['sk' => 'Viedeň', 'hu' => 'Bécs'], $this->firstHit($second, 'wien')['names_i18n']);
        $this->assertSame('Kosić', $this->firstHit($second, 'kosic', 'cc = HR')['name']);
        $this->assertArrayNotHasKey('names', $this->firstHit($second, 'kosice'));

        $this->manager->compact($second);
        $this->assertSame($count, $this->manager->status($second)->documents);

        // the swap while another process keeps searching the live uid
        $searcher = $this->startSearching($live);
        usleep(300_000);
        $publisher->publish($second);
        usleep(300_000);
        [$answered, $failures] = $this->stopSearching($searcher);
        $this->assertGreaterThan(0, $answered);
        $this->assertSame([], $failures);

        $this->assertSame($count, $this->manager->status($live)->documents);
        $this->assertFalse($this->manager->exists($second));
        $this->assertSame('Košice', $this->firstHit($live, 'kosice')['name']);

        // an abandoned build is cleaned up, a running one is kept
        $abandoned = $this->naming->buildUid($start->modify('+2 hours'));
        $running = $this->naming->buildUid($start->modify('+3 hours'));
        $this->manager->create($abandoned, new IndexSettings());
        $this->manager->create($running, new IndexSettings());
        $this->assertSame([$abandoned], $publisher->cleanup([$running]));
        $this->assertFalse($this->manager->exists($abandoned));
        $this->assertTrue($this->manager->exists($running));
        $this->assertTrue($this->manager->exists($live));
    }

    public function testRejectsADuplicateIndex(): void
    {
        $uid = $this->naming->buildUid(new \DateTimeImmutable());
        $this->manager->create($uid, new IndexSettings());

        $this->expectException(TaskFailedException::class);
        $this->expectExceptionMessage('index_already_exists');

        $this->manager->create($uid, new IndexSettings());
    }

    public function testSettingsCanBeChanged(): void
    {
        $uid = $this->naming->buildUid(new \DateTimeImmutable());
        $settings = new IndexSettings(rankingRules: ['words', 'typo', 'proximity', 'attribute', 'exactness', 'rank:desc'], maxTotalHits: 50);
        $this->manager->create($uid, new IndexSettings());

        $this->manager->applySettings($uid, $settings);

        $this->assertSame($settings->hash(), $this->manager->status($uid)->settingsHash);
    }

    #[DoesNotPerformAssertions]
    public function testSnapshot(): void
    {
        $this->manager->snapshot();
    }

    public function testWriterAlone(): void
    {
        $uid = $this->naming->buildUid(new \DateTimeImmutable());
        $this->manager->create($uid, new IndexSettings());
        $writer = new MeilisearchDocumentWriter(new MeilisearchHttp($this->url, $this->key), $uid, new IndexingOptions(batchSize: 7, maxPendingTasks: 1, pollInterval: 0.01));

        for ($i = 0; $i < 50; ++$i) {
            $writer->write($this->street('s'.$i, 'Hlavná '.$i, 'Žilina', ['Žilina', 'Zsolna'], 'SK', 49.22, 18.74));
        }
        $writer->close();

        $this->assertSame(50, $this->manager->status($uid)->documents);
    }

    /**
     * @return \Generator<Document>
     */
    private function documents(): \Generator
    {
        yield $this->place('R1', 'Bratislava', ['Bratislava', 'Pozsony', 'Pressburg'], 'SK', 48.14, 17.11, 90_900);
        yield $this->place('R2', 'Košice', ['Košice', 'Kassa', 'Kaschau'], 'SK', 48.72, 21.26, 90_800);
        yield $this->place('N3', 'Kosić', ['Kosić'], 'HR', 45.8, 15.5, 30_000);
        yield $this->place('R4', 'Wien', ['Wien', 'Viedeň', 'Vienna', 'Bécs'], 'AT', 48.21, 16.37, 90_950, ['sk' => 'Viedeň', 'hu' => 'Bécs']);
        yield $this->street('s5', 'Stephansplatz', 'Wien', ['Wien', 'Viedeň', 'Vienna', 'Bécs'], 'AT', 48.2092, 16.3727);
        yield $this->street('s6', 'Stephansplatz', 'Großkrut', ['Großkrut'], 'AT', 48.64, 16.72);
        yield $this->street('s7', 'Viedenská cesta', 'Bratislava', ['Bratislava', 'Pozsony'], 'SK', 48.13, 17.11);
        for ($i = 0; $i < self::FILLER_DOCUMENTS; ++$i) {
            yield $this->street('f'.$i, 'Ulica '.$i, 'Mesto '.($i % 50), ['Mesto '.($i % 50)], 'SK', 48.0 + $i / 10_000, 18.0);
        }
    }

    /**
     * @param list<string>          $names
     * @param array<string, string> $namesI18n
     */
    private function place(string $id, string $name, array $names, string $countryCode, float $latitude, float $longitude, int $rank, array $namesI18n = []): Document
    {
        return new Document($id, DocumentKind::Place, 'city', $name, $names, [], $namesI18n, '', '', $countryCode, null, new Coordinates($latitude, $longitude), $rank);
    }

    /**
     * @param list<string> $cities
     */
    private function street(string $id, string $name, string $city, array $cities, string $countryCode, float $latitude, float $longitude): Document
    {
        return new Document($id, DocumentKind::Street, 'street', $name, [$name], $cities, [], $city, '', $countryCode, null, new Coordinates($latitude, $longitude), 25_000);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function firstHit(string $uid, string $query, ?string $filter = null): array
    {
        $hits = $this->client->index($uid)->rawSearch($query, null === $filter ? ['limit' => 1] : ['limit' => 1, 'filter' => $filter])['hits'] ?? [];
        $this->assertIsArray($hits);
        $this->assertNotEmpty($hits, \sprintf('No hit for "%s".', $query));
        $this->assertIsArray($hits[0]);

        return $hits[0];
    }

    /**
     * A PHP process searching the live uid in a loop until its standard input is closed.
     *
     * @return array{resource, array<int, resource>}
     */
    private function startSearching(string $live): array
    {
        $code = <<<'PHP'
            [, $url, $key, $uid] = $argv;
            stream_set_blocking(STDIN, false);
            $answered = 0;
            $failures = [];
            $context = ['http' => ['method' => 'POST', 'ignore_errors' => true, 'header' => "Content-Type: application/json\r\nAuthorization: Bearer $key\r\n", 'content' => '{"q":"bratislava","limit":1}']];
            while (!feof(STDIN)) {
                fread(STDIN, 1);
                $body = @file_get_contents("$url/indexes/$uid/search", false, stream_context_create($context));
                $result = false === $body ? null : json_decode($body, true);
                if (($result['hits'][0]['name'] ?? null) === 'Bratislava') {
                    ++$answered;
                } else {
                    $failures[] = false === $body ? 'no answer' : substr($body, 0, 200);
                }
            }
            echo json_encode([$answered, array_slice($failures, 0, 5)]);
            PHP;

        $process = proc_open([\PHP_BINARY, '-r', $code, rtrim($this->url, '/'), $this->key, $live], [0 => ['pipe', 'r'], 1 => ['pipe', 'w']], $pipes);
        $this->assertIsResource($process);

        return [$process, $pipes];
    }

    /**
     * @param array{resource, array<int, resource>} $searcher
     *
     * @return array{int, list<string>}
     */
    private function stopSearching(array $searcher): array
    {
        [$process, $pipes] = $searcher;
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        proc_close($process);

        $result = json_decode((string) $output, true);
        $this->assertIsArray($result, 'The searching process failed: '.$output);
        $this->assertIsInt($result[0] ?? null);
        $this->assertIsArray($result[1] ?? null);

        return [$result[0], array_values(array_map(strval(...), $result[1]))];
    }
}
