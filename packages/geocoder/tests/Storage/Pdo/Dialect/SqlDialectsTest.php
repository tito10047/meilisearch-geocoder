<?php

namespace Tito10047\Geocoder\Tests\Storage\Pdo\Dialect;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Storage\Pdo\Dialect\MysqlDialect;
use Tito10047\Geocoder\Storage\Pdo\Dialect\PostgresqlDialect;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialectInterface;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialects;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqliteDialect;

final class SqlDialectsTest extends TestCase
{
    public function testChoosesTheDialectOfAConnection(): void
    {
        $this->assertInstanceOf(SqliteDialect::class, SqlDialects::forConnection(new \PDO('sqlite::memory:')));
    }

    /**
     * @param class-string<SqlDialectInterface> $dialect
     */
    #[DataProvider('drivers')]
    public function testChoosesTheDialectOfADriver(string $driver, string $dialect): void
    {
        $this->assertInstanceOf($dialect, SqlDialects::forDriver($driver));
    }

    /**
     * @return iterable<string, array{string, class-string<SqlDialectInterface>}>
     */
    public static function drivers(): iterable
    {
        yield 'SQLite' => ['sqlite', SqliteDialect::class];
        yield 'MariaDB/MySQL' => ['mysql', MysqlDialect::class];
        yield 'PostgreSQL' => ['pgsql', PostgresqlDialect::class];
    }

    public function testRejectsAnUnsupportedDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The database driver "oci" is not supported');

        SqlDialects::forDriver('oci');
    }
}
