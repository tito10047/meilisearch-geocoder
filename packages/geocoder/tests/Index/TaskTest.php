<?php

namespace Tito10047\Geocoder\Tests\Index;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\Task;
use Tito10047\Geocoder\Index\TaskStatus;

final class TaskTest extends TestCase
{
    public function testReadsAnEnqueuedTaskSummary(): void
    {
        $task = Task::fromArray(['taskUid' => 4, 'indexUid' => 'geo', 'status' => 'enqueued', 'type' => 'settingsUpdate', 'enqueuedAt' => '2026-10-23T08:25:49Z']);

        $this->assertEquals(new Task(4, TaskStatus::Enqueued, 'settingsUpdate', 'geo'), $task);
    }

    public function testReadsAFailedTask(): void
    {
        $task = Task::fromArray([
            'uid' => 5,
            'indexUid' => 'geo',
            'status' => 'failed',
            'type' => 'documentAdditionOrUpdate',
            'error' => ['message' => 'Document doesn\'t have a `id` attribute.', 'code' => 'missing_document_id'],
        ]);

        $this->assertSame(TaskStatus::Failed, $task->status);
        $this->assertSame('Document doesn\'t have a `id` attribute.', $task->errorMessage);
        $this->assertSame('missing_document_id', $task->errorCode);
    }

    public function testDescribe(): void
    {
        $this->assertSame('task 1 (indexSwap)', (new Task(1, TaskStatus::Succeeded, 'indexSwap'))->describe());
        $this->assertSame('task 2 (indexCompaction on geo)', (new Task(2, TaskStatus::Succeeded, 'indexCompaction', 'geo'))->describe());
    }

    public function testRejectsANegativeUid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Task(-1, TaskStatus::Enqueued, 'indexCreation');
    }

    /**
     * @param array<string, mixed> $task
     */
    #[DataProvider('provideMalformedTasks')]
    public function testRejectsMalformedTasks(array $task): void
    {
        $this->expectException(InvalidArgumentException::class);

        Task::fromArray($task);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideMalformedTasks(): iterable
    {
        yield 'no uid' => [['status' => 'enqueued', 'type' => 'indexCreation']];
        yield 'uid as string' => [['uid' => '1', 'status' => 'enqueued', 'type' => 'indexCreation']];
        yield 'unknown status' => [['uid' => 1, 'status' => 'lost', 'type' => 'indexCreation']];
        yield 'no type' => [['uid' => 1, 'status' => 'enqueued']];
        yield 'index uid as number' => [['uid' => 1, 'status' => 'enqueued', 'type' => 'indexCreation', 'indexUid' => 3]];
    }
}
