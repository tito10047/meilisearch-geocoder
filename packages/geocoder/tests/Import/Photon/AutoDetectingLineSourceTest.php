<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Import\Photon\AutoDetectingLineSource;
use Tito10047\Geocoder\Tests\Fake\InMemoryLineSource;

final class AutoDetectingLineSourceTest extends TestCase
{
    #[DataProvider('provideDumps')]
    public function testChoosesHowToRead(string $dump, bool $zstdExtension, ?string $expectedStream, ?string $expectedProcess): void
    {
        $streams = new InMemoryLineSource(['line']);
        $process = new InMemoryLineSource(['line']);

        $lines = (new AutoDetectingLineSource($streams, $process, $zstdExtension))->lines($dump);

        $this->assertSame([1 => 'line'], iterator_to_array($lines));
        $this->assertSame(null === $expectedStream ? [] : [$expectedStream], $streams->dumps);
        $this->assertSame(null === $expectedProcess ? [] : [$expectedProcess], $process->dumps);
    }

    /**
     * @return iterable<string, array{string, bool, string|null, string|null}>
     */
    public static function provideDumps(): iterable
    {
        yield 'plain file' => ['/data/slovakia.jsonl', false, '/data/slovakia.jsonl', null];
        yield 'standard input' => ['php://stdin', false, 'php://stdin', null];
        yield 'zstd without the extension' => ['/data/slovakia.jsonl.zst', false, null, '/data/slovakia.jsonl.zst'];
        yield 'zstd in upper case' => ['/data/SLOVAKIA.JSONL.ZST', false, null, '/data/SLOVAKIA.JSONL.ZST'];
        yield 'zstd with the extension' => ['/data/slovakia.jsonl.zst', true, 'compress.zstd:///data/slovakia.jsonl.zst', null];
        yield 'stream URL of a zstd file' => ['compress.zstd:///data/slovakia.jsonl.zst', false, 'compress.zstd:///data/slovakia.jsonl.zst', null];
    }

    public function testDetectsTheExtension(): void
    {
        $streams = new InMemoryLineSource();
        $process = new InMemoryLineSource();

        iterator_to_array((new AutoDetectingLineSource($streams, $process))->lines('dump.jsonl.zst'));

        $this->assertSame(\extension_loaded('zstd') ? ['compress.zstd://dump.jsonl.zst'] : [], $streams->dumps);
        $this->assertSame(\extension_loaded('zstd') ? [] : ['dump.jsonl.zst'], $process->dumps);
    }
}
