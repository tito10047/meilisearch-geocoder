<?php

namespace Tito10047\Geocoder\Storage\Pdo;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialectInterface;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialects;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Copies the tables of a build from one database into new tables of another (e.g. from the
 * SQLite file of an artifact into the MariaDB of an application, or the other way round).
 *
 * The rows go through the writers in batches, streamed. Between two SQLite databases the target
 * attaches the source file and copies with INSERT … SELECT, without PHP in between.
 */
final readonly class PdoTableCopier
{
    private const ATTACHED = 'geocoder_copy_source';

    private SqlDialectInterface $dialect;
    private PdoBuildStorage $storage;

    /**
     * @param \PDO                     $target  the database receiving the tables
     * @param SqlDialectInterface|null $dialect the dialect of the target; chosen by its driver when null
     *
     * @throws InvalidArgumentException when the connection does not throw its errors or its driver is not supported
     */
    public function __construct(
        private \PDO $target,
        ?SqlDialectInterface $dialect = null,
    ) {
        $this->dialect = $dialect ?? SqlDialects::forConnection($target);
        $this->storage = new PdoBuildStorage($target, $this->dialect);
    }

    /**
     * Creates the tables $to in the target and copies the build of the tables $from into them; on
     * failure, the tables $to are dropped.
     *
     * @return Build the build, in the tables $to
     *
     * @throws BuildNotFoundException when the source tables hold no build
     * @throws RuntimeException       when reading or writing fails, or the tables $to exist
     */
    public function copy(\PDO $source, TableNames $from, TableNames $to): Build
    {
        $build = (new PdoBuildStorage($source))->read($from)->withTables($to);

        $this->storage->create($to);
        try {
            $file = $this->sqliteFile($source);
            if (null !== $file && $this->dialect instanceof Dialect\SqliteDialect) {
                $this->copyAttached($file, $from, $to);
            } else {
                $this->copyRows($source, $from, $to);
            }
            $this->storage->complete($build);
        } catch (\Throwable $exception) {
            $this->storage->rollBack();
            try {
                $this->storage->drop($to);
            } catch (StorageException) {
                // the original failure matters more
            }

            throw $exception;
        }

        return $build;
    }

    private function copyRows(\PDO $source, TableNames $from, TableNames $to): void
    {
        $reader = new PdoTableReader($source);

        $houseNumbers = $this->storage->houseNumberWriter($to);
        foreach ($reader->houseNumbers($from) as $houseNumber) {
            $houseNumbers->write($houseNumber);
        }
        $houseNumbers->close();

        $translations = $this->storage->translationWriter($to);
        foreach ($reader->translations($from) as $translation) {
            $translations->write($translation);
        }
        $translations->close();
    }

    private function copyAttached(string $file, TableNames $from, TableNames $to): void
    {
        try {
            $attach = $this->target->prepare(\sprintf('ATTACH DATABASE ? AS %s', self::ATTACHED));
            $attach->execute([$file]);
        } catch (\PDOException $exception) {
            throw new StorageException(\sprintf('Attaching the database "%s" failed: %s', $file, $exception->getMessage()), 0, $exception);
        }

        try {
            $this->target->beginTransaction();
            $this->target->exec(\sprintf('INSERT INTO %s (street_key, number, orientation, postcode, lat, lon) SELECT street_key, number, orientation, postcode, lat, lon FROM %s.%s', $to->houseNumbers, self::ATTACHED, $from->houseNumbers));
            $this->target->exec(\sprintf('INSERT INTO %s (cc, kind, name, lang, translation) SELECT cc, kind, name, lang, translation FROM %s.%s', $to->translations, self::ATTACHED, $from->translations));
            $this->target->commit();
        } catch (\PDOException $exception) {
            if ($this->target->inTransaction()) {
                $this->target->rollBack();
            }

            throw new StorageException(\sprintf('Copying the tables of "%s" failed: %s', $file, $exception->getMessage()), 0, $exception);
        } finally {
            try {
                $this->target->exec(\sprintf('DETACH DATABASE %s', self::ATTACHED));
            } catch (\PDOException) {
                // the copy is done or failed already; an attached database is closed with the connection
            }
        }
    }

    /**
     * The file of the main database of an SQLite connection; null for another driver or an in-memory database.
     */
    private function sqliteFile(\PDO $source): ?string
    {
        if ('sqlite' !== $source->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            return null;
        }
        try {
            $file = $source->query("SELECT file FROM pragma_database_list WHERE name = 'main'", \PDO::FETCH_COLUMN, 0);
            $file = false === $file ? false : $file->fetch();
        } catch (\PDOException) {
            return null;
        }

        return \is_string($file) && '' !== $file ? $file : null;
    }
}
