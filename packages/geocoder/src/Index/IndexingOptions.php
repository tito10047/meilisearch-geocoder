<?php

namespace Tito10047\Geocoder\Index;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * How documents are sent to Meilisearch and how long its tasks are awaited.
 *
 * Meilisearch merges the enqueued document tasks of an index into one batch, and the memory of
 * a batch grows with its size: bounding the number of unfinished tasks bounds a batch to
 * maxPendingTasks × batchSize documents and keeps the memory of the indexing in check, while
 * one task is uploaded as another one is indexed.
 */
final readonly class IndexingOptions
{
    /**
     * @param int   $batchSize       documents per request (one task each)
     * @param int   $maxPendingTasks unfinished document tasks per writer before it waits for the oldest one
     * @param float $taskTimeout     seconds to wait for tasks before giving up (they keep running in Meilisearch)
     * @param float $pollInterval    seconds between two checks of unfinished tasks
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public function __construct(
        public int $batchSize = 10_000,
        public int $maxPendingTasks = 2,
        public float $taskTimeout = 3600.0,
        public float $pollInterval = 0.1,
    ) {
        if ($batchSize < 1) {
            throw new InvalidArgumentException(\sprintf('The batch size must be positive, %d given.', $batchSize));
        }
        if ($maxPendingTasks < 1) {
            throw new InvalidArgumentException(\sprintf('The maximum number of pending tasks must be positive, %d given.', $maxPendingTasks));
        }
        if (is_nan($taskTimeout) || $taskTimeout <= 0.0 || is_infinite($taskTimeout)) {
            throw new InvalidArgumentException(\sprintf('The task timeout must be a positive number of seconds, %g given.', $taskTimeout));
        }
        if (is_nan($pollInterval) || $pollInterval < 0.0 || $pollInterval > $taskTimeout) {
            throw new InvalidArgumentException(\sprintf('The poll interval must be between 0 and the task timeout, %g given.', $pollInterval));
        }
    }
}
