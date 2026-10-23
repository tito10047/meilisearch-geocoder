<?php

namespace Tito10047\Geocoder\Storage\Pdo\Dialect;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * Chooses the dialect of a connection by its driver.
 */
final class SqlDialects
{
    /**
     * @throws InvalidArgumentException when the driver is not one of SQLite, MariaDB/MySQL or PostgreSQL
     */
    public static function forConnection(\PDO $pdo): SqlDialectInterface
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        return self::forDriver(\is_string($driver) ? $driver : '');
    }

    /**
     * @param string $driver the name of a PDO driver ("sqlite", "mysql", "pgsql")
     *
     * @throws InvalidArgumentException when the driver is not one of SQLite, MariaDB/MySQL or PostgreSQL
     */
    public static function forDriver(string $driver): SqlDialectInterface
    {
        return match ($driver) {
            'sqlite' => new SqliteDialect(),
            'mysql' => new MysqlDialect(),
            'pgsql' => new PostgresqlDialect(),
            default => throw new InvalidArgumentException(\sprintf('The database driver "%s" is not supported: use SQLite, MariaDB/MySQL or PostgreSQL, or pass a dialect of your own.', $driver)),
        };
    }
}
