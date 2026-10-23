<?php

namespace Tito10047\Geocoder\Tests\Import\Workspace;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Workspace\SqliteConversionWorkspaceFactory;

final class SqliteConversionWorkspaceFactoryTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/geocoder-factory-test-'.bin2hex(random_bytes(6));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testCreatesEachWorkspaceInItsOwnFile(): void
    {
        $factory = new SqliteConversionWorkspaceFactory($this->directory);

        $first = $factory->create();
        $second = $factory->create();
        $this->assertCount(2, glob($this->directory.'/geocoder-workspace-*') ?: []);

        $this->assertTrue($first->firstOccurrence('a'));
        $this->assertTrue($second->firstOccurrence('a'));

        $first->close();
        $second->close();
        $this->assertSame([], glob($this->directory.'/*'));
    }

    public function testUsesTheTemporaryDirectoryByDefault(): void
    {
        $workspace = (new SqliteConversionWorkspaceFactory())->create();

        $this->assertTrue($workspace->firstOccurrence('a'));
        $workspace->close();
    }

    public function testRejectsAMissingDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SqliteConversionWorkspaceFactory($this->directory.'/missing');
    }
}
