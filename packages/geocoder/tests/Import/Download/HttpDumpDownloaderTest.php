<?php

namespace Tito10047\Geocoder\Tests\Import\Download;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Download\Exception\DownloadException;
use Tito10047\Geocoder\Import\Download\GraphHopperRegionUrlResolver;
use Tito10047\Geocoder\Import\Download\HttpDumpDownloader;
use Tito10047\Geocoder\Tests\Fake\PlainRegionUrlResolver;
use Tito10047\Geocoder\Tests\Fake\QueuedHttpClient;
use Tito10047\Geocoder\Tests\Fake\RecordingDownloadProgress;
use Tito10047\Geocoder\Tests\Fake\TemporaryDirectory;
use Tito10047\Geocoder\Tests\Index\Fixtures\FakeNetworkException;

final class HttpDumpDownloaderTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../Fixtures/photon';
    private const DUMP = '{"type":"NominatimDumpFile","content":{"version":"0.1.0","data_timestamp":"2026-10-17T23:02:06.000+00:00"}}'."\n"
        .'{"type":"Place","content":[]}'."\n";
    private const LAST_MODIFIED = 'Sat, 17 Oct 2026 23:30:00 GMT';

    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testDownloadsTheDump(): void
    {
        $client = new QueuedHttpClient([$this->dumpResponse()]);
        $progress = new RecordingDownloadProgress();

        $result = $this->downloader($client, chunkSize: 16, progressInterval: 64)->download('europe/slovakia', $this->directory->path, progress: $progress);

        $path = $this->directory->path.'/europe-slovakia.jsonl';
        $this->assertSame($path, $result->path);
        $this->assertTrue($result->downloaded);
        $this->assertSame(\strlen(self::DUMP), $result->bytes);
        $this->assertSame('europe/slovakia', $result->region);
        $this->assertSame('https://example.com/europe/slovakia.jsonl', $result->url);
        $this->assertEquals(new \DateTimeImmutable('2026-10-17T23:30:00+00:00'), $result->lastModified);
        $this->assertSame(self::DUMP, file_get_contents($path));

        $this->assertCount(1, $client->requests);
        $this->assertSame('GET', $client->requests[0]->getMethod());
        $this->assertFalse($client->requests[0]->hasHeader('If-None-Match'));
        $this->assertFalse($client->requests[0]->hasHeader('If-Modified-Since'));

        $state = json_decode((string) file_get_contents($path.HttpDumpDownloader::STATE_SUFFIX), true);
        $this->assertSame(['url' => 'https://example.com/europe/slovakia.jsonl', 'etag' => '"v1"', 'last_modified' => self::LAST_MODIFIED, 'bytes' => \strlen(self::DUMP)], $state);
        // only the dump and its state: the part file is gone
        $this->assertSame(['europe-slovakia.jsonl', 'europe-slovakia.jsonl.download.json'], $this->files());

        $total = \strlen(self::DUMP);
        $this->assertSame(['europe/slovakia', 0, $total], $progress->calls[0]);
        $this->assertSame(['europe/slovakia', $total, $total], $progress->calls[\count($progress->calls) - 1]);
        $this->assertGreaterThan(2, \count($progress->calls));
    }

    public function testSkipsAnUnchangedDump(): void
    {
        $client = new QueuedHttpClient([$this->dumpResponse(), new Response(304)]);
        $downloader = $this->downloader($client);
        $downloader->download('europe/slovakia', $this->directory->path);

        $result = $downloader->download('europe/slovakia', $this->directory->path);

        $this->assertFalse($result->downloaded);
        $this->assertSame(\strlen(self::DUMP), $result->bytes);
        $this->assertEquals(new \DateTimeImmutable('2026-10-17T23:30:00+00:00'), $result->lastModified);
        $this->assertSame('"v1"', $client->requests[1]->getHeaderLine('If-None-Match'));
        $this->assertSame(self::LAST_MODIFIED, $client->requests[1]->getHeaderLine('If-Modified-Since'));
        $this->assertSame(self::DUMP, file_get_contents($result->path));
    }

    public function testReplacesAChangedDump(): void
    {
        $newer = str_replace('2026-10-17', '2026-10-24', self::DUMP);
        $client = new QueuedHttpClient([$this->dumpResponse(), $this->dumpResponse($newer, '"v2"')]);
        $downloader = $this->downloader($client);
        $downloader->download('europe/slovakia', $this->directory->path);

        $result = $downloader->download('europe/slovakia', $this->directory->path);

        $this->assertTrue($result->downloaded);
        $this->assertSame($newer, file_get_contents($result->path));
        $state = json_decode((string) file_get_contents($result->path.HttpDumpDownloader::STATE_SUFFIX), true);
        $this->assertIsArray($state);
        $this->assertSame('"v2"', $state['etag']);
    }

    public function testForceDownloadsUnconditionally(): void
    {
        $client = new QueuedHttpClient([$this->dumpResponse(), $this->dumpResponse()]);
        $downloader = $this->downloader($client);
        $downloader->download('europe/slovakia', $this->directory->path);

        $this->assertTrue($downloader->download('europe/slovakia', $this->directory->path, true)->downloaded);
        $this->assertFalse($client->requests[1]->hasHeader('If-None-Match'));
    }

    public function testDownloadsUnconditionallyWhenTheDumpIsMissing(): void
    {
        $client = new QueuedHttpClient([$this->dumpResponse(), $this->dumpResponse()]);
        $downloader = $this->downloader($client);
        $first = $downloader->download('europe/slovakia', $this->directory->path);
        unlink($first->path);

        $this->assertTrue($downloader->download('europe/slovakia', $this->directory->path)->downloaded);
        $this->assertFalse($client->requests[1]->hasHeader('If-None-Match'));
    }

    public function testIgnoresAnUnreadableState(): void
    {
        file_put_contents($this->directory->path.'/europe-slovakia.jsonl', 'old');
        file_put_contents($this->directory->path.'/europe-slovakia.jsonl'.HttpDumpDownloader::STATE_SUFFIX, '{not json');
        $client = new QueuedHttpClient([$this->dumpResponse()]);

        $this->assertTrue($this->downloader($client)->download('europe/slovakia', $this->directory->path)->downloaded);
        $this->assertFalse($client->requests[0]->hasHeader('If-None-Match'));
    }

    public function testIgnoresTheStateOfAnotherUrl(): void
    {
        file_put_contents($this->directory->path.'/europe-slovakia.jsonl', 'old');
        file_put_contents($this->directory->path.'/europe-slovakia.jsonl'.HttpDumpDownloader::STATE_SUFFIX, '{"url":"https://mirror.example.org/x","etag":"\"v0\"","last_modified":null,"bytes":3}');
        $client = new QueuedHttpClient([$this->dumpResponse()]);

        $this->downloader($client)->download('europe/slovakia', $this->directory->path);

        $this->assertFalse($client->requests[0]->hasHeader('If-None-Match'));
    }

    public function testWithoutValidators(): void
    {
        $client = new QueuedHttpClient([new Response(200, [], self::DUMP), new Response(200, [], self::DUMP)]);
        $downloader = $this->downloader($client);

        $result = $downloader->download('europe/slovakia', $this->directory->path);
        $downloader->download('europe/slovakia', $this->directory->path);

        $this->assertNull($result->lastModified);
        $this->assertFalse($client->requests[1]->hasHeader('If-None-Match'));
        $this->assertFalse($client->requests[1]->hasHeader('If-Modified-Since'));
    }

    public function testCreatesTheDirectory(): void
    {
        $directory = $this->directory->path.'/dumps/new';

        $result = $this->downloader(new QueuedHttpClient([$this->dumpResponse()]))->download('europe/slovakia', $directory);

        $this->assertFileExists($result->path);
    }

    public function testDownloadsACompressedDump(): void
    {
        $dump = (string) file_get_contents(self::FIXTURES.'/slovakia-sample.jsonl.zst');
        $client = new QueuedHttpClient([new Response(200, ['Content-Length' => (string) \strlen($dump)], $dump)]);
        $downloader = new HttpDumpDownloader($client, new Psr17Factory(), new GraphHopperRegionUrlResolver('https://mirror.example.org/photon'));

        $result = $downloader->download('europe/slovakia', $this->directory->path);

        $this->assertSame($this->directory->path.'/slovakia.jsonl.zst', $result->path);
        $this->assertSame('https://mirror.example.org/photon/europe/slovakia/photon-dump-slovakia-1.0-latest.jsonl.zst', (string) $client->requests[0]->getUri());
        $this->assertSame($dump, file_get_contents($result->path));
    }

    public function testRefusesWhatIsNoDump(): void
    {
        file_put_contents($this->directory->path.'/europe-slovakia.jsonl', self::DUMP);
        $client = new QueuedHttpClient([new Response(200, [], "<html>Maintenance</html>\n")]);

        try {
            $this->downloader($client)->download('europe/slovakia', $this->directory->path, true);
            $this->fail('An HTML page is no dump.');
        } catch (DownloadException $exception) {
            $this->assertStringContainsString('is not a Photon dump', $exception->getMessage());
        }

        // the previous copy is untouched, the part removed
        $this->assertSame(self::DUMP, file_get_contents($this->directory->path.'/europe-slovakia.jsonl'));
        $this->assertSame(['europe-slovakia.jsonl'], $this->files());
    }

    public function testRefusesAnEmptyDump(): void
    {
        $this->expectException(DownloadException::class);

        $this->downloader(new QueuedHttpClient([new Response(200, [], '')]))->download('europe/slovakia', $this->directory->path);
    }

    public function testRefusesATruncatedDownload(): void
    {
        $client = new QueuedHttpClient([new Response(200, ['Content-Length' => '9999'], self::DUMP)]);

        try {
            $this->downloader($client)->download('europe/slovakia', $this->directory->path);
            $this->fail('A truncated download is refused.');
        } catch (DownloadException $exception) {
            $this->assertStringContainsString('broke off', $exception->getMessage());
        }
        $this->assertSame([], $this->files());
    }

    public function testRefusesAnErrorStatus(): void
    {
        $this->expectException(DownloadException::class);
        $this->expectExceptionMessage('404 Not Found');

        $this->downloader(new QueuedHttpClient([new Response(404)]))->download('europe/slovakia', $this->directory->path);
    }

    public function testRefusesANotModifiedAnswerToAnUnconditionalRequest(): void
    {
        $this->expectException(DownloadException::class);

        $this->downloader(new QueuedHttpClient([new Response(304)]))->download('europe/slovakia', $this->directory->path);
    }

    public function testWrapsTheClientFailures(): void
    {
        $failure = new FakeNetworkException(new Request('GET', 'https://example.com'));

        try {
            $this->downloader(new QueuedHttpClient([$failure]))->download('europe/slovakia', $this->directory->path);
            $this->fail('A network failure is a download failure.');
        } catch (DownloadException $exception) {
            $this->assertSame($failure, $exception->getPrevious());
        }
    }

    public function testRejectsAFileAsTheDirectory(): void
    {
        $file = $this->directory->path.'/file';
        touch($file);

        $this->expectException(InvalidArgumentException::class);

        $this->downloader(new QueuedHttpClient([]))->download('europe/slovakia', $file);
    }

    public function testRejectsAnInvalidRegion(): void
    {
        $downloader = new HttpDumpDownloader(new QueuedHttpClient([]), new Psr17Factory());

        $this->expectException(InvalidArgumentException::class);

        $downloader->download('../etc', $this->directory->path);
    }

    public function testRejectsInvalidSizes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new HttpDumpDownloader(new QueuedHttpClient([]), new Psr17Factory(), chunkSize: 0);
    }

    private function downloader(QueuedHttpClient $client, int $chunkSize = 1 << 20, int $progressInterval = 8 << 20): HttpDumpDownloader
    {
        return new HttpDumpDownloader($client, new Psr17Factory(), new PlainRegionUrlResolver(), chunkSize: $chunkSize, progressInterval: $progressInterval);
    }

    private function dumpResponse(string $dump = self::DUMP, string $etag = '"v1"'): Response
    {
        return new Response(200, ['Content-Length' => (string) \strlen($dump), 'ETag' => $etag, 'Last-Modified' => self::LAST_MODIFIED], $dump);
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = array_values(array_diff(scandir($this->directory->path) ?: [], ['.', '..']));
        sort($files);

        return $files;
    }
}
