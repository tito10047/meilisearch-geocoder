<?php

namespace Tito10047\Geocoder\Tests\Document;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\NdjsonDocumentWriter;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\IoException;
use Tito10047\Geocoder\Tests\Fake\Documents;

final class NdjsonDocumentWriterTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/geocoder-ndjson-test-'.bin2hex(random_bytes(6)).'.ndjson';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testWritesOneDocumentPerLine(): void
    {
        $writer = new NdjsonDocumentWriter($this->path);
        $writer->write(Documents::create(id: 'N1', name: 'Viničky'));
        $writer->write(Documents::create(id: 'N2', name: 'Szőlőske'));
        $writer->close();
        $writer->close();

        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        $this->assertIsArray($lines);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('"name":"Szőlőske"', $lines[1]);
        $this->assertEquals(Documents::create(id: 'N1', name: 'Viničky'), Document::fromArray((array) json_decode($lines[0], true)));
    }

    public function testTruncatesOrAppends(): void
    {
        file_put_contents($this->path, "old\n");

        $writer = new NdjsonDocumentWriter($this->path, append: true);
        $writer->write(Documents::create());
        $writer->close();
        $this->assertCount(2, file($this->path) ?: []);

        (new NdjsonDocumentWriter($this->path))->close();
        $this->assertSame('', file_get_contents($this->path));
    }

    public function testCannotWriteOnceClosed(): void
    {
        $writer = new NdjsonDocumentWriter($this->path);
        $writer->close();

        $this->expectException(IoException::class);

        $writer->write(Documents::create());
    }

    public function testFailsWhenTheFileCannotBeOpened(): void
    {
        $this->expectException(IoException::class);
        $this->expectExceptionMessage('Cannot open the NDJSON file "/nonexistent/documents.ndjson"');

        new NdjsonDocumentWriter('/nonexistent/documents.ndjson');
    }

    public function testRejectsAnEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new NdjsonDocumentWriter('');
    }
}
