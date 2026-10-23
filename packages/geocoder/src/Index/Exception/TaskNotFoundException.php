<?php

namespace Tito10047\Geocoder\Index\Exception;

use Tito10047\Geocoder\Exception\RuntimeException;

/**
 * Thrown when Meilisearch does not return a task that was enqueued: it was deleted meanwhile, or
 * the API key may not read it.
 *
 * A key restricted to some indexes ("indexes": ["geo*"]) does not see the tasks of no index,
 * whatever its actions: the index swaps and the snapshots ("indexUid": null). Their outcome has
 * to be read from the state of the indexes instead.
 */
final class TaskNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $taskUid)
    {
        parent::__construct(\sprintf('Meilisearch does not know the task %d: it was deleted, or the API key may not read it (a key restricted to some indexes does not see the tasks of no index, such as swaps and snapshots).', $taskUid));
    }
}
