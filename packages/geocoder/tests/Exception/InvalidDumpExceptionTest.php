<?php

namespace Tito10047\Geocoder\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidDumpException;

final class InvalidDumpExceptionTest extends TestCase
{
    public function testNamesTheLine(): void
    {
        $previous = new \JsonException('Syntax error');
        $exception = new InvalidDumpException('sk.jsonl', 12, 'invalid JSON.', $previous);

        $this->assertSame('Line 12 of the dump "sk.jsonl": invalid JSON.', $exception->getMessage());
        $this->assertSame('sk.jsonl', $exception->dump);
        $this->assertSame(12, $exception->dumpLine);
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testWithoutALine(): void
    {
        $this->assertSame('The dump "sk.jsonl": it is empty.', (new InvalidDumpException('sk.jsonl', 0, 'it is empty.'))->getMessage());
    }
}
