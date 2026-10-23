<?php

namespace Tito10047\Geocoder\Tests\Import\Download;

use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Import\Download\DownloadState;
use Tito10047\Geocoder\Tests\Fake\TemporaryDirectory;

final class DownloadStateTest extends TestCase
{
    private TemporaryDirectory $directory;

    protected function setUp(): void
    {
        $this->directory = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->directory->remove();
    }

    public function testRoundTrip(): void
    {
        $file = $this->directory->path.'/state.json';
        $state = DownloadState::fromResponse('https://example.com/a', new Response(200, ['ETag' => ' "abc" ', 'Last-Modified' => 'Sat, 17 Oct 2026 23:30:00 GMT']), 42);

        $state->write($file);

        $this->assertEquals(new DownloadState('https://example.com/a', '"abc"', 'Sat, 17 Oct 2026 23:30:00 GMT', 42), DownloadState::read($file));
        $this->assertEquals(new \DateTimeImmutable('2026-10-17T23:30:00+00:00'), $state->lastModified());
    }

    public function testWithoutValidators(): void
    {
        $state = DownloadState::fromResponse('https://example.com/a', new Response(200), 1);

        $this->assertNull($state->etag);
        $this->assertNull($state->lastModified);
        $this->assertNull($state->lastModified());
    }

    public function testAnInvalidDateIsNoDate(): void
    {
        $this->assertNull((new DownloadState('https://example.com/a', null, 'yesterday', 1))->lastModified());
    }

    #[DataProvider('provideUnreadableStates')]
    public function testAnUnreadableStateIsNone(?string $contents): void
    {
        $file = $this->directory->path.'/state.json';
        if (null !== $contents) {
            file_put_contents($file, $contents);
        }

        $this->assertNull(DownloadState::read($file));
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function provideUnreadableStates(): iterable
    {
        yield 'missing' => [null];
        yield 'not JSON' => ['{'];
        yield 'not an object' => ['"x"'];
        yield 'without URL' => ['{"bytes":1}'];
        yield 'without size' => ['{"url":"https://example.com/a"}'];
    }
}
