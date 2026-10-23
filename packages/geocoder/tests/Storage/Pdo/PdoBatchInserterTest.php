<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Storage\Pdo\PdoBatchInserter;

final class PdoBatchInserterTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->exec('CREATE TABLE t (a INTEGER NULL, b TEXT NULL, c REAL NULL)');
    }

    public function testInsertsInBatchesAndTransactions(): void
    {
        $inserter = new PdoBatchInserter($this->pdo, 'INSERT INTO t (a, b, c) VALUES ', 3, 2, 4);

        $inserter->add([1, 'one', 0.1]);
        $this->assertSame(0, $this->rowCount(), 'A row waits for its batch.');
        $inserter->add([2, null, 1e-7]);
        $this->assertSame(2, $this->rowCount());
        $this->assertTrue($this->pdo->inTransaction());
        $inserter->add([3, 'three', null]);
        $inserter->add([4, 'four', 4.0]);
        $this->assertFalse($this->pdo->inTransaction(), 'The transaction is committed after 4 rows.');
        $inserter->add([5, 'five', 5.5]);
        $inserter->flush();
        $this->assertSame(5, $this->rowCount());
        $inserter->finish();

        $this->assertFalse($this->pdo->inTransaction());
        $statement = $this->pdo->query('SELECT a, b, c FROM t ORDER BY a');
        $this->assertNotFalse($statement);
        $this->assertSame([[1, 'one', 0.1], [2, null, 1e-7], [3, 'three', null], [4, 'four', 4.0], [5, 'five', 5.5]], $statement->fetchAll(\PDO::FETCH_NUM));
    }

    public function testRollsBackItsTransactionOnFailure(): void
    {
        $this->pdo->exec('CREATE TABLE u (a INTEGER NOT NULL)');
        $inserter = new PdoBatchInserter($this->pdo, 'INSERT INTO u (a) VALUES ', 1, 2, 100);
        $inserter->add([1]);
        $inserter->add([2]);

        try {
            $inserter->add([null]);
            $inserter->add([null]);
            $this->fail('A null was inserted.');
        } catch (StorageException $exception) {
            $this->assertInstanceOf(\PDOException::class, $exception->getPrevious());
        }

        $this->assertFalse($this->pdo->inTransaction());
        $this->assertSame(0, $this->rowCount('u'));
    }

    public function testRejectsInvalidSizes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PdoBatchInserter($this->pdo, 'INSERT INTO t (a) VALUES ', 1, 1, 0);
    }

    public function testRequiresAConnectionThatThrows(): void
    {
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_WARNING);

        $this->expectException(InvalidArgumentException::class);

        new PdoBatchInserter($this->pdo, 'INSERT INTO t (a) VALUES ', 1, 1, 1);
    }

    private function rowCount(string $table = 't'): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM '.$table);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }
}
