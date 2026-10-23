<?php

namespace Tito10047\Geocoder\Index\Exception;

use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Thrown when Meilisearch tasks did not finish in time; they keep running in Meilisearch.
 */
final class TaskTimeoutException extends RuntimeException
{
    /**
     * @param list<int> $taskUids the unfinished tasks
     */
    public function __construct(public readonly array $taskUids, float $timeout)
    {
        parent::__construct(\sprintf('Meilisearch tasks %s did not finish within %s s.', implode(', ', $taskUids), $timeout));
    }
}
