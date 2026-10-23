<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialectInterface;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqliteDialect;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * SQLite, with table options SQLite refuses.
 */
final readonly class UnknownOptionsDialect implements SqlDialectInterface
{
    private SqliteDialect $sqlite;

    public function __construct()
    {
        $this->sqlite = new SqliteDialect();
    }

    public function tableOptions(): string
    {
        return ' UNKNOWN OPTIONS';
    }

    public function listTablesQuery(): string
    {
        return $this->sqlite->listTablesQuery();
    }

    public function hasTransactionalDdl(): bool
    {
        return $this->sqlite->hasTransactionalDdl();
    }

    public function publishStatements(TableNames $build, TableNames $live, array $existingLive): array
    {
        return $this->sqlite->publishStatements($build, $live, $existingLive);
    }

    public function streamingOptions(): array
    {
        return $this->sqlite->streamingOptions();
    }
}
