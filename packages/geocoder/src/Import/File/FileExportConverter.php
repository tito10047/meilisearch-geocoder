<?php

namespace Tito10047\Geocoder\Import\File;

use Tito10047\Geocoder\Document\Document;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\IoException;
use Tito10047\Geocoder\HouseNumber\HouseNumberWriterInterface;
use Tito10047\Geocoder\Import\ConverterInterface;
use Tito10047\Geocoder\Import\DumpSummary;
use Tito10047\Geocoder\Import\Exception\BuildNotFoundException;
use Tito10047\Geocoder\Import\ImportCounts;
use Tito10047\Geocoder\Import\ImportProgressInterface;
use Tito10047\Geocoder\Name\TranslationWriterInterface;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;
use Tito10047\Geocoder\Storage\Pdo\PdoSchema;
use Tito10047\Geocoder\Storage\Pdo\PdoTableReader;

/**
 * Reads an export of FileExporter as if it were a dump, so that the Importer indexes it: give
 * the Importer this converter and the directories of the exports instead of the dumps.
 *
 * It returns the summaries of the original dumps, which the export carries in its metadata (the
 * "dumps" of its build: name, data timestamp, countries and counts of each), so that a build
 * imported from an export has the same metadata as one imported from the dumps directly. Exports
 * of earlier versions carry them too, under the paths of the machine that converted the dumps:
 * only their file names are kept.
 */
final readonly class FileExportConverter implements ConverterInterface
{
    /**
     * @param int $progressInterval the progress is reported every that many documents or rows
     *
     * @throws InvalidArgumentException when the interval is not positive
     */
    public function __construct(private int $progressInterval = 100_000)
    {
        if ($progressInterval < 1) {
            throw new InvalidArgumentException('The progress interval must be positive.');
        }
    }

    /**
     * @param string $dump the directory of an export
     */
    public function convert(
        string $dump,
        DocumentWriterInterface $documents,
        HouseNumberWriterInterface $houseNumbers,
        TranslationWriterInterface $translations,
        ?ImportProgressInterface $progress = null,
    ): array {
        $documentsFile = $dump.'/'.FileExportLayout::DOCUMENTS;
        $databaseFile = $dump.'/'.FileExportLayout::DATABASE;
        if (!is_file($documentsFile) || !is_file($databaseFile)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not an export: it needs the files %s and %s.', $dump, FileExportLayout::DOCUMENTS, FileExportLayout::DATABASE));
        }

        try {
            $pdo = new \PDO('sqlite:'.$databaseFile, options: [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        } catch (\PDOException $exception) {
            throw new InvalidArgumentException(\sprintf('The database of the export "%s" cannot be opened: %s', $dump, $exception->getMessage()), 0, $exception);
        }
        $tables = FileExportLayout::tables();
        try {
            $build = (new PdoBuildStorage($pdo))->read($tables);
        } catch (BuildNotFoundException $exception) {
            throw new InvalidArgumentException(\sprintf('"%s" is not a complete export: %s', $dump, $exception->getMessage()), 0, $exception);
        }
        if (PdoSchema::VERSION !== $build->schemaVersion) {
            throw new InvalidArgumentException(\sprintf('The export "%s" has the schema version %d; this version of the library reads only %d: export the dumps again.', $dump, $build->schemaVersion, PdoSchema::VERSION));
        }

        $counts = ['places' => 0, 'streets' => 0, 'pois' => 0, 'houseNumbers' => 0];
        $report = static function () use ($dump, &$counts, $progress): void {
            $progress?->advance($dump, new ImportCounts($counts['places'], $counts['streets'], $counts['pois'], $counts['houseNumbers']));
        };

        foreach ($this->documents($documentsFile) as $document) {
            $documents->write($document);
            ++$counts[match ($document->kind) {
                DocumentKind::Place => 'places',
                DocumentKind::Street => 'streets',
                DocumentKind::Poi => 'pois',
            }];
            if (0 === ($counts['places'] + $counts['streets'] + $counts['pois']) % $this->progressInterval) {
                $report();
            }
        }

        $reader = new PdoTableReader($pdo);
        foreach ($reader->houseNumbers($tables) as $houseNumber) {
            $houseNumbers->write($houseNumber);
            if (0 === ++$counts['houseNumbers'] % $this->progressInterval) {
                $report();
            }
        }
        foreach ($reader->translations($tables) as $translation) {
            $translations->write($translation);
        }
        $report();

        $read = new ImportCounts($counts['places'], $counts['streets'], $counts['pois'], $counts['houseNumbers']);
        $expected = $build->counts();
        if ($read != $expected) {
            throw new InvalidArgumentException(\sprintf('The export "%s" is damaged: its dumps hold %s, its files %s.', $dump, $this->describe($expected), $this->describe($read)));
        }

        return array_map(
            static fn (DumpSummary $summary): DumpSummary => new DumpSummary(DumpSummary::nameOf($summary->dump), $summary->dataTimestamp, $summary->countryCodes, $summary->counts),
            $build->dumps,
        );
    }

    private function describe(ImportCounts $counts): string
    {
        return \sprintf('%d places, %d streets, %d points of interest and %d house numbers', $counts->places, $counts->streets, $counts->pois, $counts->houseNumbers);
    }

    /**
     * @return \Generator<int, Document>
     */
    private function documents(string $file): \Generator
    {
        $handle = LocalFiles::call(static fn () => fopen($file, 'r'), \sprintf('Cannot open the file "%s"', $file));
        try {
            $line = 0;
            while (false !== ($json = fgets($handle))) {
                ++$line;
                if ('' === trim($json)) {
                    continue;
                }
                try {
                    $document = json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
                    if (!\is_array($document)) {
                        throw new InvalidArgumentException('not a JSON object');
                    }

                    yield Document::fromArray($document);
                } catch (\JsonException|InvalidArgumentException $exception) {
                    throw new InvalidArgumentException(\sprintf('Line %d of "%s" is not a document: %s', $line, $file, $exception->getMessage()), 0, $exception);
                }
            }
            if (!feof($handle)) {
                throw new IoException(\sprintf('Cannot read the file "%s".', $file));
            }
        } finally {
            fclose($handle);
        }
    }
}
