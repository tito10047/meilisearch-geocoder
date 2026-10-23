<?php

namespace Tito10047\Geocoder\Storage\Pdo\Dialect;

use Tito10047\Geocoder\Storage\TableNames;

/**
 * SQLite: DDL is transactional, so the live tables are dropped and the build renamed in one
 * transaction; readers of a WAL database keep seeing the old tables until it commits.
 */
final readonly class SqliteDialect implements SqlDialectInterface
{
    public function tableOptions(): string
    {
        // the default collation BINARY compares names exactly
        return '';
    }

    public function listTablesQuery(): string
    {
        return "SELECT name FROM sqlite_master WHERE type = 'table'";
    }

    public function hasTransactionalDdl(): bool
    {
        return true;
    }

    public function publishStatements(TableNames $build, TableNames $live, array $existingLive): array
    {
        return TablePairs::dropAndRename($build, $live, $existingLive);
    }

    public function streamingOptions(): array
    {
        // pdo_sqlite steps through the result row by row
        return [];
    }
}
