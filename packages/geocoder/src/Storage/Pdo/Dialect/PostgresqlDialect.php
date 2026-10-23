<?php

namespace Tito10047\Geocoder\Storage\Pdo\Dialect;

use Tito10047\Geocoder\Storage\TableNames;

/**
 * PostgreSQL: DDL is transactional, so the live tables are dropped and the build renamed in one
 * transaction; queries waiting for the tables meanwhile find the new ones after the commit.
 *
 * The indexes keep the names they were created with (with the suffix of the build): index names
 * are unique per schema, and the suffix of every build is new.
 */
final readonly class PostgresqlDialect implements SqlDialectInterface
{
    public function tableOptions(): string
    {
        return '';
    }

    public function listTablesQuery(): string
    {
        return "SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE'";
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
        // a scrollable cursor makes pdo_pgsql declare a server-side cursor instead of fetching the whole result
        return [\PDO::ATTR_CURSOR => \PDO::CURSOR_SCROLL];
    }
}
