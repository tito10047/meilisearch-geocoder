<?php

namespace Tito10047\Geocoder\Tests\Import\Photon;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\IoException;
use Tito10047\Geocoder\Import\Photon\ZstdProcessLineSource;

final class ZstdProcessLineSourceTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../../Fixtures/photon/slovakia-sample.jsonl';

    protected function setUp(): void
    {
        exec('zstd --version 2>/dev/null', $output, $exitCode);
        if (0 !== $exitCode) {
            $this->markTestSkipped('The zstd command is not installed.');
        }
    }

    public function testDecompressesAFile(): void
    {
        $lines = iterator_to_array((new ZstdProcessLineSource())->lines(self::FIXTURE.'.zst'));

        $expected = file(self::FIXTURE, \FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($expected);
        $this->assertSame($expected, array_values($lines));
        $this->assertSame(1, array_key_first($lines));
    }

    public function testAnIterationMayBeAbandoned(): void
    {
        foreach ((new ZstdProcessLineSource())->lines(self::FIXTURE.'.zst') as $number => $line) {
            $this->assertSame(1, $number);
            break;
        }
    }

    public function testRejectsAMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a readable file');

        (new ZstdProcessLineSource())->lines('/nonexistent/dump.jsonl.zst');
    }

    public function testSurfacesTheErrorOfZstd(): void
    {
        $this->expectException(IoException::class);
        $this->expectExceptionMessageMatches('/failed after line 0 \(exit code 1\): .+/');

        // a plain file is not zstd data
        iterator_to_array((new ZstdProcessLineSource())->lines(self::FIXTURE));
    }

    public function testReportsAMissingCommand(): void
    {
        $this->expectException(IoException::class);

        iterator_to_array((new ZstdProcessLineSource('/nonexistent/zstd'))->lines(self::FIXTURE.'.zst'));
    }

    public function testRejectsAnEmptyCommand(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ZstdProcessLineSource('');
    }
}
