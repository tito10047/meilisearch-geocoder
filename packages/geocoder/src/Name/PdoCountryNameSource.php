<?php

namespace Tito10047\Geocoder\Name;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Reads the names of the imported countries from the SQL table of translations written by the
 * import (SQLite, MariaDB, PostgreSQL).
 *
 * The import writes the names of every country of the world, but the towns of the imported
 * countries only: a country is imported when the table names a town of it.
 */
final readonly class PdoCountryNameSource implements CountryNameSourceInterface
{
    public function __construct(
        private \PDO $connection,
        private TableNames $tables = new TableNames(),
    ) {
    }

    public function countryNames(): array
    {
        // the primary key (cc, kind, …) answers the EXISTS of every country with a seek
        $sql = \sprintf(
            'SELECT c.cc, c.lang, c.translation FROM %1$s c WHERE c.kind = ? AND EXISTS (SELECT 1 FROM %1$s t WHERE t.cc = c.cc AND t.kind = ?) ORDER BY c.cc, c.lang',
            $this->tables->translations,
        );

        try {
            $statement = $this->connection->prepare($sql);
            if (false === $statement) {
                throw $this->unavailable($this->connection->errorInfo());
            }
            $statement->bindValue(1, NameKind::Country->value);
            $statement->bindValue(2, NameKind::City->value);
            if (!$statement->execute()) {
                throw $this->unavailable($statement->errorInfo());
            }
            $rows = $statement->fetchAll(\PDO::FETCH_NUM);
        } catch (\PDOException $e) {
            throw new GeocoderUnavailableException(\sprintf('The names of the countries cannot be read from the table "%s": %s', $this->tables->translations, $e->getMessage()), 0, $e);
        }

        $names = [];
        foreach ($rows as $row) {
            [$countryCode, $language, $translation] = array_map(static fn (mixed $value): string => \is_scalar($value) ? trim((string) $value) : '', array_pad($row, 3, null));
            try {
                $names[] = new Translation($countryCode, NameKind::Country, $countryCode, $language, $translation);
            } catch (InvalidArgumentException) {
                // a row another tool wrote (a padded CHAR, an empty name) names no country to recognize
                continue;
            }
        }

        return $names;
    }

    /**
     * @param array<int, mixed> $errorInfo
     */
    private function unavailable(array $errorInfo): GeocoderUnavailableException
    {
        $message = $errorInfo[2] ?? null;

        return new GeocoderUnavailableException(\sprintf('The names of the countries cannot be read from the table "%s": %s', $this->tables->translations, \is_string($message) ? $message : 'unknown database error'));
    }
}
