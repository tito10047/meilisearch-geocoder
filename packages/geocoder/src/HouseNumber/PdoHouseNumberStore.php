<?php

namespace Tito10047\Geocoder\HouseNumber;

use Tito10047\Geocoder\Coordinates;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Geocoder\StreetKey;

/**
 * Reads the house numbers from the SQL table written by the import (SQLite, MariaDB, PostgreSQL).
 *
 * Both indexes of the table start with street_key, so a lookup reads the rows of one street only;
 * the prefix is then matched on them with LOWER() … LIKE, which is case-insensitive on every
 * database (PostgreSQL's LIKE is not) and needs no collation-dependent range trick.
 */
final readonly class PdoHouseNumberStore implements HouseNumberStoreInterface
{
    private const ESCAPE = '!';

    /**
     * The same house number may be stored more than once (one per OSM object): more rows are read
     * than needed and the duplicates dropped.
     */
    private const OVERFETCH = 3;

    public function __construct(
        private \PDO $connection,
        private TableNames $tables = new TableNames(),
    ) {
    }

    public function find(StreetKey $streetKey, string $number, int $limit): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException(\sprintf('The limit must be positive, %d given.', $limit));
        }

        $number = strtolower(trim($number));
        $prefix = $this->escape($number).'%';

        // the limit is an int interpolated: a bound LIMIT is quoted as a string by emulated prepares (MariaDB)
        // exact first: the number itself, then the orientation number ("42" of "606/42", the one on
        // the street signs in Czechia and Slovakia), then the conscription number ("606/…"), then prefixes
        $sql = \sprintf(
            "SELECT number, postcode, lat, lon FROM %1\$s WHERE street_key = ? AND (LOWER(number) LIKE ? ESCAPE '%3\$s' OR LOWER(orientation) LIKE ? ESCAPE '%3\$s')"
            ." ORDER BY CASE WHEN LOWER(number) = ? THEN 0 WHEN LOWER(orientation) = ? THEN 1 WHEN LOWER(number) LIKE ? ESCAPE '%3\$s' THEN 2 ELSE 3 END,"
            .' LENGTH(number), LOWER(number) LIMIT %2$d',
            $this->tables->houseNumbers,
            $limit * self::OVERFETCH,
            self::ESCAPE,
        );

        try {
            $statement = $this->connection->prepare($sql);
            if (false === $statement) {
                throw $this->unavailable($this->connection->errorInfo());
            }
            $statement->bindValue(1, $streetKey->value, \PDO::PARAM_INT);
            $statement->bindValue(2, $prefix);
            $statement->bindValue(3, $prefix);
            $statement->bindValue(4, $number);
            $statement->bindValue(5, $number);
            $statement->bindValue(6, $this->escape($number).'/%');
            if (!$statement->execute()) {
                throw $this->unavailable($statement->errorInfo());
            }
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new GeocoderUnavailableException(\sprintf('The house numbers cannot be read from the table "%s": %s', $this->tables->houseNumbers, $e->getMessage()), 0, $e);
        }

        $houseNumbers = [];
        foreach ($rows as $row) {
            $houseNumber = $this->houseNumber($streetKey, $row);
            $houseNumbers[$houseNumber->number] ??= $houseNumber;
            if (\count($houseNumbers) === $limit) {
                break;
            }
        }

        return array_values($houseNumbers);
    }

    /**
     * The text matched literally by LIKE.
     */
    private function escape(string $text): string
    {
        return strtr($text, [self::ESCAPE => self::ESCAPE.self::ESCAPE, '%' => self::ESCAPE.'%', '_' => self::ESCAPE.'_']);
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function houseNumber(StreetKey $streetKey, array $row): HouseNumber
    {
        $number = $row['number'] ?? null;
        $postcode = $row['postcode'] ?? null;
        $latitude = $row['lat'] ?? null;
        $longitude = $row['lon'] ?? null;
        if (!\is_scalar($number) || null !== $postcode && !\is_scalar($postcode) || !is_numeric($latitude) || !is_numeric($longitude)) {
            throw new GeocoderUnavailableException(\sprintf('The table "%s" holds an invalid house number of the street %d.', $this->tables->houseNumbers, $streetKey->value));
        }

        try {
            return new HouseNumber(
                $streetKey,
                (string) $number,
                null === $postcode || '' === (string) $postcode ? null : (string) $postcode,
                new Coordinates((float) $latitude, (float) $longitude),
            );
        } catch (InvalidArgumentException $e) {
            throw new GeocoderUnavailableException(\sprintf('The table "%s" holds an invalid house number of the street %d: %s', $this->tables->houseNumbers, $streetKey->value, $e->getMessage()), 0, $e);
        }
    }

    /**
     * @param array<int, mixed> $errorInfo
     */
    private function unavailable(array $errorInfo): GeocoderUnavailableException
    {
        $message = $errorInfo[2] ?? null;

        return new GeocoderUnavailableException(\sprintf('The house numbers cannot be read from the table "%s": %s', $this->tables->houseNumbers, \is_string($message) ? $message : 'unknown database error'));
    }
}
