<?php

namespace Tito10047\Geocoder\Index;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * The state of an index: its size, whether it is being indexed and with which settings.
 */
final readonly class IndexStatus
{
    /**
     * @param int         $documents          the number of documents
     * @param int         $rawDocumentDbSize  bytes of the stored documents (Meilisearch does not report the size of one index on disk)
     * @param int         $databaseSize       bytes of the whole Meilisearch database, every index included
     * @param bool        $indexing           whether a task is indexing documents now
     * @param string|null $settingsHash       IndexSettings::hash() of the settings of the index; null when they are not settings of this library
     * @param string      $meilisearchVersion "1.35.1"
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public string $uid,
        public int $documents,
        public int $rawDocumentDbSize,
        public int $databaseSize,
        public bool $indexing,
        public ?string $settingsHash,
        public string $meilisearchVersion,
    ) {
        if ($documents < 0 || $rawDocumentDbSize < 0 || $databaseSize < 0) {
            throw new InvalidArgumentException('The counts and sizes of an index must not be negative.');
        }
    }
}
