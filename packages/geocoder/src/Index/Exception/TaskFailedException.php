<?php

namespace Tito10047\Geocoder\Index\Exception;

use Tito10047\Geocoder\Exception\RuntimeException;
use Tito10047\Geocoder\Index\Task;

/**
 * Thrown when a Meilisearch task failed or was canceled: a rejected batch of documents, an
 * existing index, invalid settings, ….
 */
final class TaskFailedException extends RuntimeException
{
    public function __construct(public readonly Task $task)
    {
        parent::__construct(\sprintf(
            'Meilisearch %s %s: %s',
            $task->describe(),
            $task->status->value,
            $task->errorMessage ?? 'no reason given',
        ).(null === $task->errorCode ? '' : \sprintf(' (%s)', $task->errorCode)));
    }
}
