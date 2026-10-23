<?php

namespace Tito10047\Geocoder\Name;

use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Reads the translated names from the SQL table written by the import (SQLite, MariaDB, PostgreSQL).
 *
 * The suggestions of one search share their towns and countries: the answers are remembered by
 * the instance, up to $memoSize of them (then all forgotten at once), so that the memory of a
 * long-running worker does not grow with the catalog. The memo may outlive an import: a worker
 * should call reset() between requests (Symfony does it through the "kernel.reset" tag), or at
 * least after the data is published.
 */
final class PdoNameCatalog implements NameCatalogInterface
{
    public const DEFAULT_MEMO_SIZE = 1000;

    /**
     * @var array<string, string|null>
     */
    private array $memo = [];

    /**
     * @param int $memoSize the number of answers remembered, 0 to remember none
     *
     * @throws InvalidArgumentException when the memo size is negative
     */
    public function __construct(
        private readonly \PDO $connection,
        private readonly TableNames $tables = new TableNames(),
        private readonly int $memoSize = self::DEFAULT_MEMO_SIZE,
    ) {
        if ($memoSize < 0) {
            throw new InvalidArgumentException(\sprintf('The memo size must not be negative, %d given.', $memoSize));
        }
    }

    public function translate(string $countryCode, NameKind $kind, string $name, string $language): ?string
    {
        $key = $countryCode."\0".$kind->value."\0".$name."\0".$language;
        if (\array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $translation = $this->query($countryCode, $kind, $name, $language);

        if ($this->memoSize > 0) {
            if (\count($this->memo) >= $this->memoSize) {
                $this->memo = [];
            }
            $this->memo[$key] = $translation;
        }

        return $translation;
    }

    /**
     * Forgets every remembered answer, e.g. between the requests of a long-running worker or
     * after a publication changed the names.
     */
    public function reset(): void
    {
        $this->memo = [];
    }

    private function query(string $countryCode, NameKind $kind, string $name, string $language): ?string
    {
        try {
            $statement = $this->prepare();
            $statement->bindValue(1, $countryCode);
            $statement->bindValue(2, $kind->value);
            $statement->bindValue(3, $name);
            $statement->bindValue(4, $language);
            if (!$statement->execute()) {
                throw $this->unavailable($statement->errorInfo());
            }
            $translation = $statement->fetchColumn();
            $statement->closeCursor();
        } catch (\PDOException $e) {
            throw new GeocoderUnavailableException(\sprintf('The names cannot be read from the table "%s": %s', $this->tables->translations, $e->getMessage()), 0, $e);
        }

        return \is_string($translation) && '' !== $translation ? $translation : null;
    }

    private function prepare(): \PDOStatement
    {
        $statement = $this->connection->prepare(\sprintf(
            'SELECT translation FROM %s WHERE cc = ? AND kind = ? AND name = ? AND lang = ?',
            $this->tables->translations,
        ));
        if (false === $statement) {
            throw $this->unavailable($this->connection->errorInfo());
        }

        return $statement;
    }

    /**
     * @param array<int, mixed> $errorInfo
     */
    private function unavailable(array $errorInfo): GeocoderUnavailableException
    {
        $message = $errorInfo[2] ?? null;

        return new GeocoderUnavailableException(\sprintf('The names cannot be read from the table "%s": %s', $this->tables->translations, \is_string($message) ? $message : 'unknown database error'));
    }
}
