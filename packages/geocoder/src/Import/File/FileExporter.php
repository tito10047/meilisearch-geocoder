<?php

namespace Tito10047\Geocoder\Import\File;

use Tito10047\Geocoder\Document\NdjsonDocumentWriter;
use Tito10047\Geocoder\Exception\ExceptionInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Exception\StorageException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\ClockInterface;
use Tito10047\Geocoder\Import\ConverterInterface;
use Tito10047\Geocoder\Import\ImportProgressInterface;
use Tito10047\Geocoder\Import\SystemClock;
use Tito10047\Geocoder\Index\IndexNamingInterface;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;

/**
 * Converts dumps into an NDJSON file of documents and an SQLite database of the tables.
 *
 * The same conversion as the Importer's, into files: the build of an export carries the settings
 * hash of the index it is meant for and the uid it would get.
 */
final readonly class FileExporter implements FileExporterInterface
{
    public function __construct(
        private ConverterInterface $converter,
        private IndexNamingInterface $naming = new TimestampIndexNaming(),
        private IndexSettings $settings = new IndexSettings(),
        private ClockInterface $clock = new SystemClock(),
    ) {
    }

    public function export(iterable $dumps, string $directory, ?ImportProgressInterface $progress = null): Build
    {
        $dumps = [...$dumps];
        if ([] === $dumps) {
            throw new InvalidArgumentException('Nothing to export: give at least one dump.');
        }

        $created = LocalFiles::prepareEmptyDirectory($directory);
        $documentsFile = $directory.'/'.FileExportLayout::DOCUMENTS;
        $databaseFile = $directory.'/'.FileExportLayout::DATABASE;
        $now = $this->clock->now();
        // to the second, like the name of the build and the metadata
        $startedAt = $now->setTime((int) $now->format('G'), (int) $now->format('i'), (int) $now->format('s'));
        $tables = FileExportLayout::tables();
        $pdo = null;

        try {
            try {
                $pdo = new \PDO('sqlite:'.$databaseFile, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
                // a new file: if the export fails it is deleted, nothing to protect from a crash
                $pdo->exec('PRAGMA journal_mode = OFF');
                $pdo->exec('PRAGMA synchronous = OFF');
            } catch (\PDOException $exception) {
                throw new StorageException(\sprintf('Cannot create the database "%s": %s', $databaseFile, $exception->getMessage()), 0, $exception);
            }
            $storage = new PdoBuildStorage($pdo);
            $storage->create($tables);

            $documents = new NdjsonDocumentWriter($documentsFile);
            $houseNumbers = $storage->houseNumberWriter($tables);
            $translations = $storage->translationWriter($tables);
            $summaries = [];
            foreach ($dumps as $dump) {
                array_push($summaries, ...$this->converter->convert($dump, $documents, $houseNumbers, $translations, $progress));
            }
            $documents->close();
            $houseNumbers->close();
            $translations->close();

            $build = new Build($this->naming->buildUid($startedAt), $tables, $startedAt, $this->settings->hash(), $storage->schemaVersion(), $summaries);
            $storage->complete($build);

            return $build;
        } catch (\Throwable $exception) {
            $pdo = null;
            unset($documents, $houseNumbers, $translations, $storage);
            LocalFiles::removeQuietly([$documentsFile, $databaseFile], $created ? $directory : null);

            if ($exception instanceof ExceptionInterface || !$exception instanceof \Exception) {
                throw $exception;
            }

            throw new RuntimeException(\sprintf('The export into "%s" failed: %s', $directory, $exception->getMessage()), 0, $exception);
        }
    }
}
