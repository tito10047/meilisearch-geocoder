<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Photon\StreamLineSource;

final class StreamLineSourceTest extends TestCase
{
    public function testReadsTheLinesWithTheirNumbersAndSkipsEmptyOnes(): void
    {
        $lines = (new StreamLineSource())->lines('data://text/plain;base64,'.base64_encode("first\r\n\nthird\nlast"));

        $this->assertSame([1 => 'first', 3 => 'third', 4 => 'last'], iterator_to_array($lines));
    }

    public function testReadsAFile(): void
    {
        $lines = iterator_to_array((new StreamLineSource())->lines(__DIR__.'/../../Fixtures/photon/slovakia-sample.jsonl'));

        $this->assertCount(200, $lines);
        $this->assertStringStartsWith('{"type":"NominatimDumpFile"', $lines[1]);
    }

    public function testRejectsAMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The dump "/nonexistent/dump.jsonl" cannot be opened');

        (new StreamLineSource())->lines('/nonexistent/dump.jsonl');
    }

    public function testRejectsADirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is a directory');

        (new StreamLineSource())->lines(__DIR__);
    }
}
