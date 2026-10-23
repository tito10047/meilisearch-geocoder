<?php

namespace Tito10047\Geocoder\Index;

use Tito10047\Geocoder\Exception\InvalidArgumentException;

/**
 * An asynchronous Meilisearch task: an index creation, a batch of documents, a swap, ….
 */
final readonly class Task
{
    /**
     * @param int         $uid          the task uid
     * @param string      $type         "documentAdditionOrUpdate", "indexSwap", …
     * @param string|null $indexUid     the index the task works on; null for a swap or a snapshot
     * @param string|null $errorMessage why the task failed
     * @param string|null $errorCode    the Meilisearch error code ("missing_document_id", …)
     *
     * @throws InvalidArgumentException when the uid is negative
     */
    public function __construct(
        public int $uid,
        public TaskStatus $status,
        public string $type,
        public ?string $indexUid = null,
        public ?string $errorMessage = null,
        public ?string $errorCode = null,
    ) {
        if ($uid < 0) {
            throw new InvalidArgumentException(\sprintf('The uid of a task must not be negative, %d given.', $uid));
        }
    }

    /**
     * Reads a task returned by Meilisearch: a summary of an enqueued task ("taskUid") or a task
     * of GET /tasks ("uid").
     *
     * @param array<array-key, mixed> $task
     *
     * @throws InvalidArgumentException when the task is malformed
     */
    public static function fromArray(array $task): self
    {
        $uid = $task['uid'] ?? $task['taskUid'] ?? null;
        $status = \is_string($task['status'] ?? null) ? TaskStatus::tryFrom($task['status']) : null;
        $type = $task['type'] ?? null;
        $indexUid = $task['indexUid'] ?? null;
        if (!\is_int($uid) || null === $status || !\is_string($type) || (null !== $indexUid && !\is_string($indexUid))) {
            throw new InvalidArgumentException('The task returned by Meilisearch is malformed.');
        }

        $error = $task['error'] ?? null;
        $message = \is_array($error) && \is_string($error['message'] ?? null) ? $error['message'] : null;
        $code = \is_array($error) && \is_string($error['code'] ?? null) ? $error['code'] : null;

        return new self($uid, $status, $type, $indexUid, $message, $code);
    }

    /**
     * A short description for logs and exception messages: "task 42 (documentAdditionOrUpdate on geo_20261023120000)".
     */
    public function describe(): string
    {
        return \sprintf('task %d (%s%s)', $this->uid, $this->type, null === $this->indexUid ? '' : ' on '.$this->indexUid);
    }
}
