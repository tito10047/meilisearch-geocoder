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
final readonly class PdoHouseNumberStore implements HouseNumberStoreInterface, HouseNumberReferenceStoreInterface
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
            "SELECT street_key, number, postcode, lat, lon, suburb, reference FROM %1\$s WHERE street_key = ? AND (LOWER(number) LIKE ? ESCAPE '%3\$s' OR LOWER(orientation) LIKE ? ESCAPE '%3\$s')"
            ." ORDER BY CASE WHEN LOWER(number) = ? THEN 0 WHEN LOWER(orientation) = ? THEN 1 WHEN LOWER(number) LIKE ? ESCAPE '%3\$s' THEN 2 ELSE 3 END,"
            .' LENGTH(number), LOWER(number) LIMIT %2$d',
            $this->tables->houseNumbers,
            $limit * self::OVERFETCH,
            self::ESCAPE,
        );

        $rows = $this->rows($sql, [
            [$streetKey->value, \PDO::PARAM_INT],
            [$prefix, \PDO::PARAM_STR],
            [$prefix, \PDO::PARAM_STR],
            [$number, \PDO::PARAM_STR],
            [$number, \PDO::PARAM_STR],
            [$this->escape($number).'/%', \PDO::PARAM_STR],
        ]);

        $houseNumbers = [];
        foreach ($rows as $row) {
            $houseNumber = $this->houseNumber($row);
            $houseNumbers[$houseNumber->number] ??= $houseNumber;
            if (\count($houseNumbers) === $limit) {
                break;
            }
        }

        return array_values($houseNumbers);
    }

    public function findByReference(string $reference): ?HouseNumber
    {
        // the same house may be several OSM objects: any of them
        $sql = \sprintf('SELECT street_key, number, postcode, lat, lon, suburb, reference FROM %s WHERE reference = ? LIMIT 1', $this->tables->houseNumbers);
        $rows = $this->rows($sql, [[$reference, \PDO::PARAM_STR]]);

        return [] === $rows ? null : $this->houseNumber($rows[0]);
    }

    /**
     * @param list<array{int|string, int}> $parameters the values and their PDO types
     *
     * @return list<array<array-key, mixed>>
     */
    private function rows(string $sql, array $parameters): array
    {
        try {
            $statement = $this->connection->prepare($sql);
            if (false === $statement) {
                throw $this->unavailable($this->connection->errorInfo());
            }
            foreach ($parameters as $index => [$value, $type]) {
                $statement->bindValue($index + 1, $value, $type);
            }
            if (!$statement->execute()) {
                throw $this->unavailable($statement->errorInfo());
            }

            return array_values($statement->fetchAll(\PDO::FETCH_ASSOC));
        } catch (\PDOException $e) {
            throw new GeocoderUnavailableException(\sprintf('The house numbers cannot be read from the table "%s": %s', $this->tables->houseNumbers, $e->getMessage()), 0, $e);
        }
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
    private function houseNumber(array $row): HouseNumber
    {
        $streetKey = $row['street_key'] ?? null;
        $number = $row['number'] ?? null;
        $postcode = $row['postcode'] ?? null;
        $latitude = $row['lat'] ?? null;
        $longitude = $row['lon'] ?? null;
        $suburb = $row['suburb'] ?? null;
        $reference = $row['reference'] ?? null;
        if (!is_numeric($streetKey) || !\is_scalar($number) || !is_numeric($latitude) || !is_numeric($longitude)
            || null !== $postcode && !\is_scalar($postcode) || null !== $suburb && !\is_scalar($suburb) || null !== $reference && !\is_scalar($reference)) {
            throw new GeocoderUnavailableException(\sprintf('The table "%s" holds an invalid house number.', $this->tables->houseNumbers));
        }

        try {
            return new HouseNumber(
                new StreetKey((int) $streetKey),
                (string) $number,
                $this->optional($postcode),
                new Coordinates((float) $latitude, (float) $longitude),
                $this->optional($suburb),
                $this->optional($reference),
            );
        } catch (InvalidArgumentException $e) {
            throw new GeocoderUnavailableException(\sprintf('The table "%s" holds an invalid house number of the street %s: %s', $this->tables->houseNumbers, (string) $streetKey, $e->getMessage()), 0, $e);
        }
    }

    /**
     * A nullable column: an empty value is none.
     *
     * @param scalar|null $value
     */
    private function optional(mixed $value): ?string
    {
        return null === $value || '' === (string) $value ? null : (string) $value;
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
