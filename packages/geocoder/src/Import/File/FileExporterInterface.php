<?php

namespace Tito10047\Geocoder\Import\File;

use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Import\Build;
use Tito10047\Geocoder\Import\ImportProgressInterface;

/**
 * Converts dumps into files, without a search engine: to convert on one machine and index on
 * another, or to feed the documents to a tool of one's own.
 *
 * The directory holds "documents.ndjson" (the documents, one JSON object per line, as stored in
 * Meilisearch) and "geocoder.sqlite" (the house numbers, the translations and the metadata of
 * the build). FileExportConverter imports it like a dump.
 */
interface FileExporterInterface
{
    /**
     * @param iterable<string> $dumps     paths or stream URLs ("php://stdin"); ".zst" is decompressed
     * @param string           $directory a directory that does not exist or is empty
     *
     * @return Build the build of the export: its index uid is the one the documents are meant for, no index exists
     *
     * @throws InvalidArgumentException when there is no dump, the directory is not empty, or a dump is not a dump
     * @throws RuntimeException         when the conversion or writing the files fails (the files are removed)
     */
    public function export(iterable $dumps, string $directory, ?ImportProgressInterface $progress = null): Build;
}
