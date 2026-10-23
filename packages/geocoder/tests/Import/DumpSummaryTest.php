<?php

namespace Tito10047\Geocoder\Tests\Import;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Import\DumpSummary;

final class DumpSummaryTest extends TestCase
{
    #[DataProvider('dumps')]
    public function testNamesADumpWithoutTheDirectoriesOfTheMachine(string $dump, string $name): void
    {
        $this->assertSame($name, DumpSummary::nameOf($dump));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dumps(): iterable
    {
        yield 'absolute path' => ['/srv/geo/dumps/slovakia.jsonl.zst', 'slovakia.jsonl.zst'];
        yield 'relative path' => ['var/geo/slovakia.jsonl.zst', 'slovakia.jsonl.zst'];
        yield 'file name' => ['slovakia.jsonl.zst', 'slovakia.jsonl.zst'];
        yield 'directory of an export' => ['/srv/exports/europe-2026-10/', 'europe-2026-10'];
        yield 'Windows path' => ['C:\\geo\\austria.jsonl', 'austria.jsonl'];
        yield 'file URL' => ['file:///srv/geo/austria.jsonl', 'austria.jsonl'];
        yield 'URL with a query' => ['https://example.com/dumps/italy.jsonl.zst?token=secret#x', 'italy.jsonl.zst'];
        yield 'standard input' => ['php://stdin', 'php://stdin'];
        yield 'root' => ['/', '/'];
    }
}
