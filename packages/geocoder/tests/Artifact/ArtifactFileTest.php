<?php

namespace Tito10047\Geocoder\Tests\Artifact;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Artifact\ArtifactFile;
use Tito10047\Geocoder\Exception\InvalidArgumentException;

final class ArtifactFileTest extends TestCase
{
    public function testKeepsAValidFile(): void
    {
        $file = new ArtifactFile('geocoder.sqlite', 0, hash('sha256', ''));

        $this->assertSame('geocoder.sqlite', $file->name);
        $this->assertSame(0, $file->size);
    }

    #[DataProvider('invalidFiles')]
    public function testRejectsAnInvalidFile(string $name, int $size, string $sha256): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ArtifactFile($name, $size, $sha256);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidFiles(): iterable
    {
        $hash = hash('sha256', '');

        yield 'empty name' => ['', 1, $hash];
        yield 'a path' => ['a/b', 1, $hash];
        yield 'the parent' => ['..', 1, $hash];
        yield 'negative size' => ['a', -1, $hash];
        yield 'upper-case hash' => ['a', 1, strtoupper($hash)];
        yield 'short hash' => ['a', 1, 'abc'];
    }
}
