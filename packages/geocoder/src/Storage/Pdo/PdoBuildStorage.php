<?php

namespace Tito10047\Geocoder\Storage\Pdo;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\HouseNumber\HouseNumberWriterInterface;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Name\TranslationWriterInterface;
use Tito10047\Geocoder\Storage\BuildStorageInterface;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialectInterface;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialects;
use Tito10047\Geocoder\Storage\Pdo\Dialect\TablePairs;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * The builds in a database reached through PDO: SQLite, MariaDB/MySQL or PostgreSQL, the
 * differences being those of the dialect.
 */
final readonly class PdoBuildStorage implements BuildStorageInterface
{
    private SqlDialectInterface $dialect;
    private PdoSchema $schema;
    private PdoBuildMetadata $metadata;

    /**
     * @param SqlDialectInterface|null $dialect                    the dialect of the connection; chosen by its driver when null
     * @param int                      $houseNumberBatchSize       the rows of one INSERT of house numbers
     * @param int                      $houseNumberTransactionSize the rows of one transaction of house numbers
     *
     * @throws InvalidArgumentException when the connection does not throw its errors, its driver is not supported or a size is not positive
     */
    public function __construct(
        private \PDO $pdo,
        ?SqlDialectInterface $dialect = null,
        private int $houseNumberBatchSize = 500,
        private int $houseNumberTransactionSize = 50_000,
    ) {
        if ($houseNumberBatchSize < 1 || $houseNumberTransactionSize < 1) {
            throw new InvalidArgumentException('The batch and transaction sizes of the house numbers must be positive.');
        }
        $this->dialect = $dialect ?? SqlDialects::forConnection($pdo);
        $this->schema = new PdoSchema($pdo, $this->dialect);
        $this->metadata = new PdoBuildMetadata($pdo);
    }

    public function schemaVersion(): int
    {
        return PdoSchema::VERSION;
    }

    public function create(TableNames $tables): void
    {
        if ($this->exists($tables)) {
            throw new StorageException(\sprintf('The tables %s, %s and %s cannot be created: one of them exists already.', $tables->houseNumbers, $tables->translations, $tables->metadata));
        }
        $this->schema->createTables($tables);
    }

    public function houseNumberWriter(TableNames $tables): HouseNumberWriterInterface
    {
        return new PdoHouseNumberWriter($this->pdo, $tables, $this->houseNumberBatchSize, $this->houseNumberTransactionSize);
    }

    public function translationWriter(TableNames $tables): TranslationWriterInterface
    {
        return new PdoTranslationWriter($this->pdo, $tables);
    }

    public function complete(Build $build): void
    {
        $this->schema->createIndexes($build->tables);
        $this->metadata->write($build);
    }

    public function read(TableNames $tables): Build
    {
        $this->assertBuild($tables);

        return $this->metadata->read($tables);
    }

    public function exists(TableNames $tables): bool
    {
        $existing = $this->tables();
        foreach (TablePairs::of($tables, $tables) as [$table]) {
            if (TablePairs::contains($existing, $table)) {
                return true;
            }
        }

        return false;
    }

    public function recordPublication(TableNames $tables, string $indexFingerprint): void
    {
        $this->assertBuild($tables);
        $this->metadata->writePublication($tables, $indexFingerprint);
    }

    public function recordedPublication(TableNames $tables): ?string
    {
        $this->assertBuild($tables);

        return $this->metadata->readPublication($tables);
    }

    public function publish(TableNames $build, TableNames $live): void
    {
        $existing = $this->tables();
        $existingLive = [];
        foreach (TablePairs::of($build, $live) as [$buildTable, $liveTable]) {
            if (!TablePairs::contains($existing, $buildTable)) {
                throw new BuildNotFoundException(\sprintf('The table %s of the build does not exist.', $buildTable));
            }
            if (TablePairs::contains($existing, $liveTable)) {
                $existingLive[] = $liveTable;
            }
        }

        $statements = $this->dialect->publishStatements($build, $live, $existingLive);
        if (!$this->dialect->hasTransactionalDdl()) {
            $this->execute($statements);

            return;
        }

        try {
            $this->pdo->beginTransaction();
            $this->execute($statements);
            $this->pdo->commit();
        } catch (\PDOException|StorageException $exception) {
            $this->rollBack();

            throw $exception instanceof StorageException ? $exception : new StorageException(\sprintf('Publishing the tables %s failed: %s', $build->houseNumbers, $exception->getMessage()), 0, $exception);
        }
    }

    public function drop(TableNames $tables): void
    {
        $this->schema->dropTables($tables);
    }

    public function suffixes(TableNames $tables): array
    {
        $suffixes = [];
        foreach ($this->tables() as $table) {
            foreach (TablePairs::of($tables, $tables) as [$name]) {
                if (\strlen($table) > \strlen($name) && 0 === strncasecmp($table, $name, \strlen($name))) {
                    $suffixes[substr($table, \strlen($name))] = true;
                }
            }
        }
        $suffixes = array_map(strval(...), array_keys($suffixes));
        sort($suffixes);

        return $suffixes;
    }

    public function rollBack(): void
    {
        try {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } catch (\PDOException) {
            // nothing more can be done: the caller is handling a failure already
        }
    }

    /**
     * @throws BuildNotFoundException when the metadata table does not exist
     */
    private function assertBuild(TableNames $tables): void
    {
        if (!TablePairs::contains($this->tables(), $tables->metadata)) {
            throw new BuildNotFoundException(\sprintf('There is no build in the tables %s: the table %s does not exist.', $tables->houseNumbers, $tables->metadata));
        }
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        try {
            $statement = $this->pdo->query($this->dialect->listTablesQuery(), \PDO::FETCH_COLUMN, 0);
            $tables = [];
            foreach ($statement ?: [] as $name) {
                if (\is_string($name)) {
                    $tables[] = $name;
                }
            }

            return $tables;
        } catch (\PDOException $exception) {
            throw new StorageException(\sprintf('Listing the tables of the database failed: %s', $exception->getMessage()), 0, $exception);
        }
    }

    /**
     * @param list<string> $statements
     */
    private function execute(array $statements): void
    {
        foreach ($statements as $statement) {
            try {
                // the identifiers come from TableNames, which validates them
                $this->pdo->exec($statement);
            } catch (\PDOException $exception) {
                throw new StorageException(\sprintf('The database refused "%s": %s', $statement, $exception->getMessage()), 0, $exception);
            }
        }
    }
}
