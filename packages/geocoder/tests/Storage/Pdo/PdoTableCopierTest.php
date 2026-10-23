<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\Pdo\PdoTableCopier;
use Tito10047\Geocoder\Storage\Pdo\PdoTableReader;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\Builds;
use Tito10047\Geocoder\Tests\Fake\Sql;

final class PdoTableCopierTest extends TestCase
{
    private string $file;
    private TableNames $from;
    private TableNames $to;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/geocoder-copier-'.bin2hex(random_bytes(4)).'.sqlite';
        $this->from = new TableNames();
        $this->to = new TableNames('h_1', 'n_1', 'm_1');
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testCopiesTheRowsThroughTheWriters(): void
    {
        $source = new \PDO('sqlite::memory:');
        $this->assertCopies($source, Builds::store($source, $this->from));
    }

    public function testCopiesBetweenSqliteFilesByAttaching(): void
    {
        $source = new \PDO('sqlite:'.$this->file);
        $this->assertCopies($source, Builds::store($source, $this->from));
    }

    public function testRefusesSourceTablesWithoutABuild(): void
    {
        $target = new \PDO('sqlite::memory:');

        $this->expectException(BuildNotFoundException::class);

        (new PdoTableCopier($target))->copy(new \PDO('sqlite::memory:'), $this->from, $this->to);
    }

    public function testDropsTheCopyWhenItFails(): void
    {
        $source = new \PDO('sqlite::memory:');
        Builds::store($source, $this->from);
        $source->exec("INSERT INTO geo_house (street_key, number, lat, lon) VALUES (1, ' ', 1, 1)");
        $target = new \PDO('sqlite::memory:');

        try {
            (new PdoTableCopier($target))->copy($source, $this->from, $this->to);
            $this->fail('An invalid row was copied.');
        } catch (StorageException) {
        }

        $this->assertFalse((new PdoBuildStorage($target))->exists($this->to));
        $this->assertFalse($target->inTransaction());
    }

    private function assertCopies(\PDO $source, Build $build): void
    {
        $target = new \PDO('sqlite::memory:');

        $copy = (new PdoTableCopier($target))->copy($source, $this->from, $this->to);

        $this->assertEquals($build->withTables($this->to), $copy);
        $this->assertEquals($copy, (new PdoBuildStorage($target))->read($this->to));
        $sourceReader = new PdoTableReader($source);
        $targetReader = new PdoTableReader($target);
        $this->assertEquals(iterator_to_array($sourceReader->houseNumbers($this->from), false), iterator_to_array($targetReader->houseNumbers($this->to), false));
        $this->assertEquals(iterator_to_array($sourceReader->translations($this->from), false), iterator_to_array($targetReader->translations($this->to), false));
        $this->assertSame('2', Sql::value($target, "SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND tbl_name = 'h_1'"));
        // the source is detached
        $this->assertSame('1', Sql::value($target, 'SELECT COUNT(*) FROM pragma_database_list'));
    }
}
