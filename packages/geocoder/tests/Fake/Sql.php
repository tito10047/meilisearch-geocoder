<?php

namespace Tito10047\Geocoder\Tests\Fake;

/**
 * Queries of the tests.
 */
final class Sql
{
    /**
     * The first column of the first row.
     */
    public static function value(\PDO $pdo, string $sql): string
    {
        $statement = $pdo->query($sql);
        if (false === $statement) {
            throw new \LogicException(\sprintf('The query "%s" failed.', $sql));
        }

        return (string) $statement->fetchColumn();
    }

    /**
     * The first column of every row.
     *
     * @return list<string>
     */
    public static function column(\PDO $pdo, string $sql): array
    {
        $statement = $pdo->query($sql);
        if (false === $statement) {
            throw new \LogicException(\sprintf('The query "%s" failed.', $sql));
        }

        return array_values(array_map(strval(...), $statement->fetchAll(\PDO::FETCH_COLUMN)));
    }
}
