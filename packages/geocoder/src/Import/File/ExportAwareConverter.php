<?php

namespace Tito10047\Geocoder\Import\File;

use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\HouseNumber\HouseNumberWriterInterface;
use Tito10047\Geocoder\Import\ConverterInterface;
use Tito10047\Geocoder\Import\DumpSummary;
use Tito10047\Geocoder\Import\ImportProgressInterface;
use Tito10047\Geocoder\Name\TranslationWriterInterface;

/**
 * Imports the directories of exports (FileExporter) and the dumps alike: an export goes to the
 * converter of exports, anything else to the converter of dumps.
 *
 * So one Importer indexes both, e.g. a dump converted on another machine (its export) next to a
 * dump converted here.
 */
final readonly class ExportAwareConverter implements ConverterInterface
{
    public function __construct(
        private ConverterInterface $dumps,
        private ConverterInterface $exports = new FileExportConverter(),
    ) {
    }

    /**
     * Whether the path is the directory of an export: it holds the documents and the database.
     */
    public static function isExport(string $path): bool
    {
        return is_dir($path)
            && is_file($path.'/'.FileExportLayout::DOCUMENTS)
            && is_file($path.'/'.FileExportLayout::DATABASE);
    }

    public function convert(
        string $dump,
        DocumentWriterInterface $documents,
        HouseNumberWriterInterface $houseNumbers,
        TranslationWriterInterface $translations,
        ?ImportProgressInterface $progress = null,
    ): DumpSummary {
        $converter = self::isExport($dump) ? $this->exports : $this->dumps;

        return $converter->convert($dump, $documents, $houseNumbers, $translations, $progress);
    }
}
