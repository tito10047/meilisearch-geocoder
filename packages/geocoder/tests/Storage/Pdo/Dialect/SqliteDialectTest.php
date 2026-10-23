<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo\Dialect;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqliteDialect;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\Tests\Fake\Sql;

final class SqliteDialectTest extends TestCase
{
    public function testListsTheTables(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE a (x INTEGER)');
        $pdo->exec('CREATE INDEX a_x ON a (x)');
        $statement = $pdo->query((new SqliteDialect())->listTablesQuery());
        $this->assertNotFalse($statement);

        $this->assertSame(['a'], $statement->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * The statements run against SQLite: the first publication renames, the next one replaces.
     */
    public function testPublishesInATransaction(): void
    {
        $dialect = new SqliteDialect();
        $this->assertTrue($dialect->hasTransactionalDdl());
        $this->assertSame('', $dialect->tableOptions());
        $this->assertSame([], $dialect->streamingOptions());

        $pdo = new \PDO('sqlite::memory:');
        $live = new TableNames('h', 'n', 'm');
        foreach (['h_1', 'n_1', 'm_1', 'h', 'n'] as $table) {
            $pdo->exec(\sprintf("CREATE TABLE %s (v TEXT); INSERT INTO %1\$s VALUES ('%1\$s')", $table));
        }

        $statements = $dialect->publishStatements($live->withSuffix('_1'), $live, ['h', 'n']);
        $this->assertSame(['DROP TABLE h', 'DROP TABLE n', 'ALTER TABLE h_1 RENAME TO h', 'ALTER TABLE n_1 RENAME TO n', 'ALTER TABLE m_1 RENAME TO m'], $statements);

        $pdo->beginTransaction();
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }
        $pdo->commit();

        foreach (['h', 'n', 'm'] as $table) {
            $this->assertSame($table.'_1', Sql::value($pdo, 'SELECT v FROM '.$table));
        }
    }
}
