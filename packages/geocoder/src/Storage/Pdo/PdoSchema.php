<?php

namespace Tito10047\Geocoder\Storage\Pdo;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Creates and drops the tables of the geocoder, in SQL that SQLite, MariaDB/MySQL and PostgreSQL share.
 *
 * The secondary indexes of the house numbers are created apart, once the table is loaded: tens
 * of millions of rows load much faster into a table without them.
 */
final readonly class PdoSchema
{
    /**
     * @throws InvalidArgumentException when the connection does not throw its errors
     */
    public function __construct(
        private \PDO $pdo,
    ) {
        PdoErrorMode::assertExceptions($pdo);
    }

    /**
     * Creates the tables, without the secondary indexes.
     *
     * @throws StorageException when a table cannot be created (e.g. it exists)
     */
    public function createTables(TableNames $tables): void
    {
        $this->execute([
            \sprintf('CREATE TABLE %s (street_key BIGINT NOT NULL, number VARCHAR(32) NOT NULL, orientation VARCHAR(32) NULL, postcode VARCHAR(16) NULL, lat DOUBLE PRECISION NOT NULL, lon DOUBLE PRECISION NOT NULL)', $tables->houseNumbers),
            \sprintf('CREATE TABLE %s (cc CHAR(2) NOT NULL, kind VARCHAR(8) NOT NULL, name VARCHAR(255) NOT NULL, lang VARCHAR(3) NOT NULL, translation VARCHAR(255) NOT NULL, PRIMARY KEY (cc, kind, name, lang))', $tables->translations),
            \sprintf('CREATE TABLE %s (meta_key VARCHAR(64) NOT NULL PRIMARY KEY, meta_value TEXT NOT NULL)', $tables->metadata),
        ]);
    }

    /**
     * Creates the secondary indexes, once the tables are loaded.
     *
     * @throws StorageException when an index cannot be created
     */
    public function createIndexes(TableNames $tables): void
    {
        $this->execute([
            \sprintf('CREATE INDEX %1$s_number ON %1$s (street_key, number)', $tables->houseNumbers),
            \sprintf('CREATE INDEX %1$s_orientation ON %1$s (street_key, orientation)', $tables->houseNumbers),
        ]);
    }

    /**
     * Drops the tables that exist, with their indexes.
     *
     * @throws StorageException when a table cannot be dropped
     */
    public function dropTables(TableNames $tables): void
    {
        $this->execute([
            \sprintf('DROP TABLE IF EXISTS %s', $tables->houseNumbers),
            \sprintf('DROP TABLE IF EXISTS %s', $tables->translations),
            \sprintf('DROP TABLE IF EXISTS %s', $tables->metadata),
        ]);
    }

    /**
     * @param list<string> $statements
     */
    private function execute(array $statements): void
    {
        foreach ($statements as $statement) {
            try {
                // statements without parameters: the identifiers come from TableNames, which validates them
                $this->pdo->exec($statement);
            } catch (\PDOException $exception) {
                throw new StorageException(\sprintf('The database refused "%s": %s', $statement, $exception->getMessage()), 0, $exception);
            }
        }
    }
}
