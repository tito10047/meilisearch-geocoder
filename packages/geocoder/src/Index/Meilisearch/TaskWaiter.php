<?php

namespace Tito10047\Geocoder\Index\Meilisearch;

use Tito10047\Geocoder\Index\Exception\MeilisearchException;
use Tito10047\Geocoder\Index\Exception\TaskFailedException;
use Tito10047\Geocoder\Index\Exception\TaskTimeoutException;
use Tito10047\Geocoder\Index\IndexingOptions;
use Tito10047\Geocoder\Index\Task;
use Tito10047\Geocoder\Index\TaskStatus;

/**
 * Polls Meilisearch until its asynchronous tasks finish and fails loudly on a failed one.
 *
 * @internal
 */
final readonly class TaskWaiter
{
    public function __construct(
        private MeilisearchApi $api,
        private IndexingOptions $options,
    ) {
    }

    /**
     * Waits until every task finished; one request per poll whatever their number.
     *
     * @param list<int> $taskUids
     *
     * @throws TaskFailedException  when a task failed or was canceled
     * @throws TaskTimeoutException when the tasks did not finish within the timeout
     * @throws MeilisearchException when Meilisearch cannot be asked
     */
    public function wait(array $taskUids): void
    {
        $this->waitUntilAtMost($taskUids, 0);
    }

    /**
     * Waits until at most $unfinished of the tasks are unfinished, and returns those, oldest first.
     *
     * @param list<int> $taskUids
     *
     * @return list<int>
     *
     * @throws TaskFailedException  when a finished task failed or was canceled
     * @throws TaskTimeoutException when the tasks did not finish within the timeout
     * @throws MeilisearchException when Meilisearch cannot be asked
     */
    public function waitUntilAtMost(array $taskUids, int $unfinished): array
    {
        $deadline = hrtime(true) + (int) ($this->options->taskTimeout * 1e9);
        $pending = $taskUids;
        while ([] !== $pending && \count($pending) > $unfinished) {
            $pending = $this->unfinished($pending);
            if (\count($pending) <= $unfinished) {
                break;
            }
            if (hrtime(true) >= $deadline) {
                throw new TaskTimeoutException($pending, $this->options->taskTimeout);
            }
            usleep((int) ($this->options->pollInterval * 1e6));
        }

        return $pending;
    }

    /**
     * @param non-empty-list<int> $taskUids
     *
     * @return list<int>
     */
    private function unfinished(array $taskUids): array
    {
        $finished = [];
        foreach ($this->api->tasks($taskUids) as $task) {
            if (!$task->status->isFinished()) {
                $finished[$task->uid] = false;
                continue;
            }
            if (TaskStatus::Succeeded !== $task->status) {
                throw new TaskFailedException($task);
            }
            $finished[$task->uid] = true;
        }

        $unfinished = [];
        foreach ($taskUids as $uid) {
            if (!isset($finished[$uid])) {
                // a deleted task would otherwise be awaited until the timeout
                throw new MeilisearchException(\sprintf('Meilisearch does not know the task %d.', $uid));
            }
            if (!$finished[$uid]) {
                $unfinished[] = $uid;
            }
        }

        return $unfinished;
    }

    /**
     * Waits for one task.
     *
     * @throws TaskFailedException  when the task failed or was canceled
     * @throws TaskTimeoutException when the task did not finish within the timeout
     * @throws MeilisearchException when Meilisearch cannot be asked
     */
    public function waitFor(Task $task): void
    {
        $this->wait([$task->uid]);
    }
}
