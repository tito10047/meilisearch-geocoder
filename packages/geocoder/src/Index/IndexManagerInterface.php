<?php

namespace Tito10047\Geocoder\Index;

use Tito10047\Geocoder\Document\DocumentWriterInterface;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Builds and maintains the search indexes of the geocoder.
 *
 * Every method returns once the work is done in the search engine (its asynchronous tasks
 * finished) and throws when one of them failed, except documentWriter(), whose writer is
 * complete once closed.
 */
interface IndexManagerInterface
{
    /**
     * Creates an empty index with the primary key "id" and applies the settings, before any
     * document: changing the searchable attributes of a filled index re-indexes it.
     *
     * @throws InvalidArgumentException when the uid is not a valid index uid
     * @throws RuntimeException         when the index cannot be created (it exists already, …)
     */
    public function create(string $uid, IndexSettings $settings): void;

    /**
     * Replaces the settings of an existing index; cheap for the ranking rules, a full re-indexing
     * for the searchable or filterable attributes.
     *
     * @throws InvalidArgumentException when the uid is not a valid index uid
     * @throws RuntimeException         when the settings cannot be applied
     */
    public function applySettings(string $uid, IndexSettings $settings): void;

    /**
     * A writer sending documents to the index in batches; they are all indexed once its close()
     * returned.
     *
     * @throws InvalidArgumentException when the uid is not a valid index uid
     */
    public function documentWriter(string $uid): DocumentWriterInterface;

    /**
     * Waits until no task of the index is enqueued or processing.
     *
     * @throws InvalidArgumentException when the uid is not a valid index uid
     * @throws RuntimeException         when a task failed or the tasks did not finish in time
     */
    public function waitForTasks(string $uid): void;

    /**
     * Compacts the index: reclaims the free pages left by indexing (about −20 % after a build).
     *
     * @throws InvalidArgumentException when the uid is not a valid index uid
     * @throws RuntimeException         when the index cannot be compacted
     */
    public function compact(string $uid): void;

    /**
     * Makes the documents of $uid live under $liveUid at once, without a moment of downtime:
     * searches keep querying $liveUid. Afterwards $uid holds the former live index, or does not
     * exist when there was none (the index was renamed).
     *
     * Returns once the swap took effect, also with an API key that may not read the swap itself
     * (a key restricted to some indexes): its effect is then read from the fingerprints of the
     * indexes.
     *
     * @throws InvalidArgumentException when a uid is not a valid index uid or both are equal
     * @throws RuntimeException         when the indexes cannot be swapped
     */
    public function swap(string $uid, string $liveUid): void;

    /**
     * Deletes the index and its documents.
     *
     * @throws InvalidArgumentException when the uid is not a valid index uid
     * @throws RuntimeException         when the index cannot be deleted (it does not exist, …)
     */
    public function delete(string $uid): void;

    /**
     * @throws InvalidArgumentException when the uid is not a valid index uid
     * @throws RuntimeException         when the search engine cannot be asked
     */
    public function exists(string $uid): bool;

    /**
     * A value telling the index apart from every other one, which moves with its documents when
     * it is swapped or renamed: comparing it before and after tells whether a swap took effect,
     * or which build a live index holds.
     *
     * @return string|null null when the index does not exist
     *
     * @throws InvalidArgumentException when the uid is not a valid index uid
     * @throws RuntimeException         when the search engine cannot be asked
     */
    public function fingerprint(string $uid): ?string;

    /**
     * The version of the search engine ("1.35.1"); it also tells that the engine is reachable.
     *
     * @throws RuntimeException when the search engine cannot be asked
     */
    public function version(): string;

    /**
     * The uids of every index of the search engine, the indexes of other applications included.
     *
     * @return list<string>
     *
     * @throws RuntimeException when the search engine cannot be asked
     */
    public function indexUids(): array;

    /**
     * @throws InvalidArgumentException when the uid is not a valid index uid
     * @throws RuntimeException         when the index does not exist or the search engine cannot be asked
     */
    public function status(string $uid): IndexStatus;

    /**
     * Creates a snapshot of the whole search engine (every index) in its snapshot directory, to
     * be loaded by a new instance of the same version.
     *
     * Needs an API key allowed on every index: a key restricted to some indexes cannot follow the
     * snapshot to its end.
     *
     * @throws RuntimeException when the snapshot cannot be created or followed
     */
    public function snapshot(): void;
}
