<?php

namespace Tito10047\Geocoder\Storage\Pdo\Dialect;

use Tito10047\Geocoder\Storage\TableNames;

/**
 * What differs between the databases the geocoder supports: the options of its tables, how
 * their names are listed and how a build replaces the live tables.
 *
 * A dialect only returns SQL; the caller executes it. The identifiers come from TableNames,
 * which validates them as plain identifiers.
 */
interface SqlDialectInterface
{
    /**
     * Appended to every CREATE TABLE of the geocoder ("" when the defaults are right).
     */
    public function tableOptions(): string;

    /**
     * A query without parameters listing the names of the tables of the current database (or
     * schema), in one column.
     */
    public function listTablesQuery(): string;

    /**
     * Whether the statements of publishStatements() run in one transaction because the database
     * rolls back its DDL. Otherwise each statement commits on its own and the statements make the
     * replacement atomic by themselves.
     */
    public function hasTransactionalDdl(): bool;

    /**
     * The statements renaming the tables of a build to the live names and dropping the live
     * tables they replace.
     *
     * @param list<string> $existingLive the live tables that exist (none on a first publication)
     *
     * @return list<string>
     */
    public function publishStatements(TableNames $build, TableNames $live, array $existingLive): array;

    /**
     * The driver options of a SELECT that streams its rows instead of loading the whole result
     * into the memory of PHP.
     *
     * @return array<int, mixed>
     */
    public function streamingOptions(): array;
}
