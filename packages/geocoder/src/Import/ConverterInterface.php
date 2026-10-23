<?php

namespace Tito10047\Geocoder\Import;

use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\HouseNumber\HouseNumberWriterInterface;
use Tito10047\Geocoder\Name\TranslationWriterInterface;

/**
 * Turns a Photon dump into documents, house numbers and translated names.
 */
interface ConverterInterface
{
    /**
     * Reads the whole dump and writes what it selects to the writers.
     *
     * The writers belong to the caller, who may pass the same writers for several dumps: the
     * converter never closes them.
     *
     * @param string $dump a file path or a stream URL ("php://stdin"); a ".zst" file is decompressed
     *
     * @return non-empty-list<DumpSummary> what the dump contained: one summary for a dump, one per
     *                                     original dump for what holds several converted dumps
     *                                     (an export of FileExporter); named by DumpSummary::nameOf()
     *
     * @throws InvalidArgumentException when the dump cannot be opened or is not a Photon dump
     * @throws RuntimeException         when reading the dump or writing its contents fails
     */
    public function convert(
        string $dump,
        DocumentWriterInterface $documents,
        HouseNumberWriterInterface $houseNumbers,
        TranslationWriterInterface $translations,
        ?ImportProgressInterface $progress = null,
    ): array;
}
