<?php

namespace Tito10047\Geocoder\Storage\Pdo\Dialect;

use Tito10047\Geocoder\Storage\TableNames;

/**
 * MariaDB and MySQL (InnoDB).
 *
 * Their DDL commits on its own, but one RENAME TABLE of several pairs is atomic: the live tables
 * are renamed away and the build renamed in at once, and the old tables dropped afterwards.
 *
 * The tables get the binary collation utf8mb4_bin: with the default case- and accent-insensitive
 * collation, the translations of "Sered" and "Sereď" would be one key (a duplicate key error) and
 * the lookups would not match those of SQLite and PostgreSQL, which compare exactly.
 */
final readonly class MysqlDialect implements SqlDialectInterface
{
    /** The suffix of the replaced live tables between the rename and their drop. */
    private const REPLACED_SUFFIX = '_old';

    /**
     * Pdo\Mysql::ATTR_USE_BUFFERED_QUERY, formerly PDO::MYSQL_ATTR_USE_BUFFERED_QUERY: the name
     * depends on the version of PHP and the constant exists only where pdo_mysql is loaded.
     */
    public const ATTR_USE_BUFFERED_QUERY = 1000;

    public function tableOptions(): string
    {
        return ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
    }

    public function listTablesQuery(): string
    {
        return "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'";
    }

    public function hasTransactionalDdl(): bool
    {
        return false;
    }

    public function publishStatements(TableNames $build, TableNames $live, array $existingLive): array
    {
        // shorter than the suffix of a build: valid whenever the names of the build are
        $replaced = $live->withSuffix(self::REPLACED_SUFFIX);
        $buildTables = TablePairs::of($build, $live);
        $renames = [];
        $drops = [];
        foreach (TablePairs::of($live, $replaced) as $role => [$liveTable, $replacedTable]) {
            if (TablePairs::contains($existingLive, $liveTable)) {
                $renames[] = \sprintf('%s TO %s', $liveTable, $replacedTable);
                $drops[] = $replacedTable;
            }
            $renames[] = \sprintf('%s TO %s', $buildTables[$role][0], $liveTable);
        }

        $statements = [];
        if ([] !== $drops) {
            // left by a publication that failed between its rename and its drop
            $statements[] = 'DROP TABLE IF EXISTS '.implode(', ', $drops);
        }
        $statements[] = 'RENAME TABLE '.implode(', ', $renames);
        if ([] !== $drops) {
            $statements[] = 'DROP TABLE '.implode(', ', $drops);
        }

        return $statements;
    }

    public function streamingOptions(): array
    {
        // an unbuffered query: the rows come from the server as they are fetched
        return [self::ATTR_USE_BUFFERED_QUERY => false];
    }
}
