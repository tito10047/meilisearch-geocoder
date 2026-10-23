<?php

namespace Tito10047\Geocoder\Index;

/**
 * The state of an asynchronous Meilisearch task.
 */
enum TaskStatus: string
{
    case Enqueued = 'enqueued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Canceled = 'canceled';

    /**
     * Whether the task will not change any more.
     */
    public function isFinished(): bool
    {
        return self::Enqueued !== $this && self::Processing !== $this;
    }
}
