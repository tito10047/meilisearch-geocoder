<?php

namespace Tito10047\Geocoder\Index\Meilisearch;

use Tito10047\Geocoder\Index\Exception\MeilisearchException;
use Tito10047\Geocoder\Index\Exception\TaskFailedException;
use Tito10047\Geocoder\Index\Exception\TaskNotFoundException;
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
     * @throws TaskFailedException   when a task failed or was canceled
     * @throws TaskNotFoundException when Meilisearch does not return a task (deleted, or hidden from the API key)
     * @throws TaskTimeoutException  when the tasks did not finish within the timeout
     * @throws MeilisearchException  when Meilisearch cannot be asked
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
     * @throws TaskFailedException   when a finished task failed or was canceled
     * @throws TaskNotFoundException when Meilisearch does not return a task (deleted, or hidden from the API key)
     * @throws TaskTimeoutException  when the tasks did not finish within the timeout
     * @throws MeilisearchException  when Meilisearch cannot be asked
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
                throw new TaskNotFoundException($uid);
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
     * @throws TaskFailedException   when the task failed or was canceled
     * @throws TaskNotFoundException when Meilisearch does not return the task (deleted, or hidden from the API key)
     * @throws TaskTimeoutException  when the task did not finish within the timeout
     * @throws MeilisearchException  when Meilisearch cannot be asked
     */
    public function waitFor(Task $task): void
    {
        $this->wait([$task->uid]);
    }

    /**
     * Polls a condition on the state of the indexes until it holds: the outcome of a task the API
     * key cannot read (see TaskNotFoundException), with the timeout of the tasks.
     *
     * @param \Closure(): bool $condition whether the task took effect; asks Meilisearch
     * @param Task             $task      the task whose effect is awaited
     * @param string           $effect    what the condition checks, for the timeout message: "the index a replaced the index b"
     *
     * @throws TaskTimeoutException when the condition did not hold within the timeout
     * @throws MeilisearchException when Meilisearch cannot be asked
     */
    public function waitForEffect(\Closure $condition, Task $task, string $effect): void
    {
        $deadline = hrtime(true) + (int) ($this->options->taskTimeout * 1e9);
        while (!$condition()) {
            if (hrtime(true) >= $deadline) {
                throw new TaskTimeoutException([$task->uid], $this->options->taskTimeout, \sprintf('Meilisearch %s cannot be read with this API key, and its effect (%s) did not appear within %s s: it failed, or it is still waiting in the queue.', $task->describe(), $effect, $this->options->taskTimeout));
            }
            usleep((int) ($this->options->pollInterval * 1e6));
        }
    }
}
