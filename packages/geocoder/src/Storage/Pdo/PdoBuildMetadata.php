<?php

namespace Tito10047\Geocoder\Storage\Pdo;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\DumpSummary;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Writes a build into the key/value metadata table of its tables, and reads it back.
 *
 * The build itself is the key "dumps" (JSON) with the keys of the index; the summaries
 * ("data_timestamp.SK", "count.streets", …) are written for people and status queries reading
 * the table, and ignored when it is read.
 *
 * @internal
 */
final readonly class PdoBuildMetadata
{
    private const SCHEMA_VERSION = 'schema_version';
    private const INDEX_UID = 'index_uid';
    private const STARTED_AT = 'started_at';
    private const SETTINGS_HASH = 'settings_hash';
    private const DUMPS = 'dumps';
    private const DATA_TIMESTAMP_PREFIX = 'data_timestamp.';
    private const COUNT_PREFIX = 'count.';
    private const PUBLICATION = 'publication.index_fingerprint';
    private const TIMESTAMP_FORMAT = \DateTimeInterface::ATOM;

    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Replaces the metadata in $build->tables by that of the build.
     *
     * @throws StorageException when the metadata cannot be written
     */
    public function write(Build $build): void
    {
        $counts = $build->counts();
        $values = [
            self::SCHEMA_VERSION => (string) $build->schemaVersion,
            self::INDEX_UID => $build->indexUid,
            self::STARTED_AT => $this->timestamp($build->startedAt),
            self::SETTINGS_HASH => $build->settingsHash,
            self::DUMPS => json_encode(array_map($this->dump(...), $build->dumps), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR),
            self::COUNT_PREFIX.'places' => (string) $counts->places,
            self::COUNT_PREFIX.'streets' => (string) $counts->streets,
            self::COUNT_PREFIX.'pois' => (string) $counts->pois,
            self::COUNT_PREFIX.'house_numbers' => (string) $counts->houseNumbers,
        ];
        foreach ($build->dataTimestamps() as $countryCode => $timestamp) {
            $values[self::DATA_TIMESTAMP_PREFIX.$countryCode] = $this->timestamp($timestamp);
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }
            $this->pdo->exec(\sprintf('DELETE FROM %s', $build->tables->metadata));
            $insert = $this->pdo->prepare(\sprintf('INSERT INTO %s (meta_key, meta_value) VALUES (?, ?)', $build->tables->metadata));
            foreach ($values as $key => $value) {
                $insert->execute([$key, $value]);
            }
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\PDOException $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw new StorageException(\sprintf('Writing the metadata of the build into %s failed: %s', $build->tables->metadata, $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * @throws BuildNotFoundException when the table holds no build
     * @throws StorageException       when the metadata cannot be read or is invalid
     */
    public function read(TableNames $tables): Build
    {
        try {
            $statement = $this->pdo->query(\sprintf('SELECT meta_key, meta_value FROM %s', $tables->metadata), \PDO::FETCH_NUM);
            $values = [];
            foreach ($statement ?: [] as $row) {
                if (\is_array($row) && \is_string($row[0] ?? null) && \is_string($row[1] ?? null)) {
                    $values[$row[0]] = $row[1];
                }
            }
        } catch (\PDOException $exception) {
            throw new StorageException(\sprintf('Reading the metadata of the build from %s failed: %s', $tables->metadata, $exception->getMessage()), 0, $exception);
        }

        if (!isset($values[self::INDEX_UID])) {
            throw new BuildNotFoundException(\sprintf('The table %s holds no build.', $tables->metadata));
        }

        try {
            $dumps = json_decode($values[self::DUMPS] ?? '', true, 16, \JSON_THROW_ON_ERROR);
            if (!\is_array($dumps)) {
                throw new InvalidArgumentException('the dumps are not a list');
            }

            return new Build(
                $values[self::INDEX_UID],
                $tables,
                $this->parseTimestamp($values[self::STARTED_AT] ?? ''),
                $values[self::SETTINGS_HASH] ?? '',
                (int) ($values[self::SCHEMA_VERSION] ?? 0),
                array_values(array_map($this->parseDump(...), $dumps)),
            );
        } catch (InvalidArgumentException|\JsonException $exception) {
            throw new StorageException(\sprintf('The metadata of the build in %s is invalid: %s', $tables->metadata, $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * Records the fingerprint of the index of the build whose publication starts, replacing an
     * earlier one.
     *
     * @throws BuildNotFoundException when the table holds no build
     * @throws StorageException       when it cannot be written
     */
    public function writePublication(TableNames $tables, string $indexFingerprint): void
    {
        if ('' === $indexFingerprint) {
            throw new InvalidArgumentException('The fingerprint of the index of a build must not be empty.');
        }

        $ownsTransaction = !$this->pdo->inTransaction();
        try {
            if ($ownsTransaction) {
                $this->pdo->beginTransaction();
            }
            if (null === $this->value($tables, self::INDEX_UID)) {
                throw new BuildNotFoundException(\sprintf('The table %s holds no build.', $tables->metadata));
            }
            $this->pdo->prepare(\sprintf('DELETE FROM %s WHERE meta_key = ?', $tables->metadata))->execute([self::PUBLICATION]);
            $this->pdo->prepare(\sprintf('INSERT INTO %s (meta_key, meta_value) VALUES (?, ?)', $tables->metadata))->execute([self::PUBLICATION, $indexFingerprint]);
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
        } catch (\PDOException|BuildNotFoundException $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($exception instanceof BuildNotFoundException) {
                throw $exception;
            }

            throw new StorageException(\sprintf('Recording the publication of the build in %s failed: %s', $tables->metadata, $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * The fingerprint recorded by writePublication(), if any.
     *
     * @throws BuildNotFoundException when the table holds no build
     * @throws StorageException       when it cannot be read
     */
    public function readPublication(TableNames $tables): ?string
    {
        try {
            if (null === $this->value($tables, self::INDEX_UID)) {
                throw new BuildNotFoundException(\sprintf('The table %s holds no build.', $tables->metadata));
            }

            return $this->value($tables, self::PUBLICATION);
        } catch (\PDOException $exception) {
            throw new StorageException(\sprintf('Reading the publication of the build from %s failed: %s', $tables->metadata, $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * @throws \PDOException
     */
    private function value(TableNames $tables, string $key): ?string
    {
        $statement = $this->pdo->prepare(\sprintf('SELECT meta_value FROM %s WHERE meta_key = ?', $tables->metadata));
        $statement->execute([$key]);
        $value = $statement->fetchColumn();
        $statement->closeCursor();

        return \is_string($value) ? $value : null;
    }

    /**
     * @return array{dump: string, data_timestamp: string, countries: list<string>, counts: array{places: int, streets: int, pois: int, house_numbers: int}}
     */
    private function dump(DumpSummary $dump): array
    {
        return [
            'dump' => $dump->dump,
            'data_timestamp' => $this->timestamp($dump->dataTimestamp),
            'countries' => $dump->countryCodes,
            'counts' => [
                'places' => $dump->counts->places,
                'streets' => $dump->counts->streets,
                'pois' => $dump->counts->pois,
                'house_numbers' => $dump->counts->houseNumbers,
            ],
        ];
    }

    private function parseDump(mixed $dump): DumpSummary
    {
        if (!\is_array($dump) || !\is_string($dump['dump'] ?? null) || !\is_string($dump['data_timestamp'] ?? null)
            || !\is_array($dump['countries'] ?? null) || !\is_array($counts = $dump['counts'] ?? null)) {
            throw new InvalidArgumentException('a dump misses a field');
        }
        $countryCodes = [];
        foreach ($dump['countries'] as $countryCode) {
            if (!\is_string($countryCode)) {
                throw new InvalidArgumentException('a country code is not a string');
            }
            $countryCodes[] = $countryCode;
        }
        foreach (['places', 'streets', 'pois', 'house_numbers'] as $count) {
            if (!\is_int($counts[$count] ?? null)) {
                throw new InvalidArgumentException(\sprintf('the count "%s" of a dump is not an integer', $count));
            }
        }

        return new DumpSummary(
            $dump['dump'],
            $this->parseTimestamp($dump['data_timestamp']),
            $countryCodes,
            new ImportCounts($counts['places'], $counts['streets'], $counts['pois'], $counts['house_numbers']),
        );
    }

    private function timestamp(\DateTimeImmutable $timestamp): string
    {
        return $timestamp->setTimezone(new \DateTimeZone('UTC'))->format(self::TIMESTAMP_FORMAT);
    }

    private function parseTimestamp(string $timestamp): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!'.self::TIMESTAMP_FORMAT, $timestamp);
        if (false === $parsed) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a timestamp', $timestamp));
        }

        return $parsed;
    }
}
