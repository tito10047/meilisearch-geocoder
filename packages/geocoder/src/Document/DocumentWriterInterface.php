<?php

namespace Tito10047\Geocoder\Document;

use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Receives the documents of an import: an NDJSON file, a Meilisearch index, ….
 *
 * Implementations may buffer the documents: they are complete only once close() returned.
 */
interface DocumentWriterInterface
{
    /**
     * @throws RuntimeException when the document cannot be written
     */
    public function write(Document $document): void;

    /**
     * Writes the buffered documents and releases the resources; nothing may be written afterwards.
     *
     * @throws RuntimeException when the documents cannot be written
     */
    public function close(): void;
}
