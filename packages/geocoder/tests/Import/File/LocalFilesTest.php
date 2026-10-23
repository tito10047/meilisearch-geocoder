<?php

namespace Tito10047\Geocoder\Tests\Import\File;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\IoException;
use Tito10047\Geocoder\Import\File\LocalFiles;
use Tito10047\Geocoder\Tests\Fake\TemporaryDirectory;

final class LocalFilesTest extends TestCase
{
    private TemporaryDirectory $temporary;

    protected function setUp(): void
    {
        $this->temporary = new TemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->temporary->remove();
    }

    public function testCreatesADirectoryOrAcceptsAnEmptyOne(): void
    {
        $this->assertTrue(LocalFiles::prepareEmptyDirectory($this->temporary->path.'/a/b'));
        $this->assertDirectoryExists($this->temporary->path.'/a/b');
        $this->assertFalse(LocalFiles::prepareEmptyDirectory($this->temporary->path.'/a/b'));
    }

    public function testRefusesADirectoryThatIsNotEmpty(): void
    {
        touch($this->temporary->path.'/x');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not empty');

        LocalFiles::prepareEmptyDirectory($this->temporary->path);
    }

    public function testRefusesAFile(): void
    {
        touch($this->temporary->path.'/file');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a directory');

        LocalFiles::prepareEmptyDirectory($this->temporary->path.'/file');
    }

    public function testRefusesAnEmptyPath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocalFiles::prepareEmptyDirectory('');
    }

    public function testCopiesAFileAndHashesIt(): void
    {
        LocalFiles::write($this->temporary->path.'/a', str_repeat('geocoder', 300_000));

        $hash = LocalFiles::copy($this->temporary->path.'/a', $this->temporary->path.'/b');

        $this->assertSame(hash('sha256', str_repeat('geocoder', 300_000)), $hash);
        $this->assertSame($hash, LocalFiles::sha256($this->temporary->path.'/b'));
        $this->assertSame(2_400_000, LocalFiles::size($this->temporary->path.'/b'));
        $this->assertSame(str_repeat('geocoder', 300_000), LocalFiles::read($this->temporary->path.'/b'));
    }

    public function testRefusesToOverwriteWhenCopying(): void
    {
        LocalFiles::write($this->temporary->path.'/a', 'a');
        LocalFiles::write($this->temporary->path.'/b', 'b');

        $this->expectException(IoException::class);
        $this->expectExceptionMessage('Cannot create the file');

        LocalFiles::copy($this->temporary->path.'/a', $this->temporary->path.'/b');
    }

    public function testTellsWhyAnOperationFailed(): void
    {
        $this->expectException(IoException::class);
        $this->expectExceptionMessageMatches('/^Cannot read the file ".*missing": .+/');

        LocalFiles::read($this->temporary->path.'/missing');
    }

    public function testRemovesQuietly(): void
    {
        $directory = $this->temporary->path.'/d';
        mkdir($directory);
        touch($directory.'/a');

        LocalFiles::removeQuietly([$directory.'/a', $directory.'/missing'], $directory);

        $this->assertDirectoryDoesNotExist($directory);
    }
}
