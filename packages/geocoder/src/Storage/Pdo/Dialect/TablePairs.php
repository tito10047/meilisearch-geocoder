<?php

namespace Tito10047\Geocoder\Storage\Pdo\Dialect;

use Tito10047\Geocoder\Storage\TableNames;

/**
 * Helpers shared by the dialects.
 *
 * @internal
 */
final class TablePairs
{
    /**
     * The tables of one set next to the tables of the same role in the other.
     *
     * @return list<array{string, string}>
     */
    public static function of(TableNames $from, TableNames $to): array
    {
        return [
            [$from->houseNumbers, $to->houseNumbers],
            [$from->translations, $to->translations],
            [$from->metadata, $to->metadata],
        ];
    }

    /**
     * Whether the table is in the list, whatever the case: PostgreSQL folds unquoted names to
     * lower case, MySQL does on some file systems.
     *
     * @param list<string> $tables
     */
    public static function contains(array $tables, string $table): bool
    {
        foreach ($tables as $candidate) {
            if (0 === strcasecmp($candidate, $table)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The publication of the databases with transactional DDL: drop the live tables, rename the
     * build, all in one transaction of the caller.
     *
     * @param list<string> $existingLive
     *
     * @return list<string>
     */
    public static function dropAndRename(TableNames $build, TableNames $live, array $existingLive): array
    {
        $statements = [];
        foreach (self::of($build, $live) as [, $liveTable]) {
            if (self::contains($existingLive, $liveTable)) {
                $statements[] = \sprintf('DROP TABLE %s', $liveTable);
            }
        }
        foreach (self::of($build, $live) as [$buildTable, $liveTable]) {
            $statements[] = \sprintf('ALTER TABLE %s RENAME TO %s', $buildTable, $liveTable);
        }

        return $statements;
    }
}
