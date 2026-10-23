<?php

namespace Tito10047\Geocoder\Tests\Index\Exception;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Index\Exception\TaskFailedException;
use Tito10047\Geocoder\Index\Task;
use Tito10047\Geocoder\Index\TaskStatus;

final class TaskFailedExceptionTest extends TestCase
{
    public function testDescribesTheTask(): void
    {
        $task = new Task(5, TaskStatus::Failed, 'documentAdditionOrUpdate', 'geo', 'Document doesn\'t have a `id` attribute.', 'missing_document_id');

        $exception = new TaskFailedException($task);

        $this->assertSame($task, $exception->task);
        $this->assertSame('Meilisearch task 5 (documentAdditionOrUpdate on geo) failed: Document doesn\'t have a `id` attribute. (missing_document_id)', $exception->getMessage());
    }

    public function testCanceledTaskWithoutReason(): void
    {
        $exception = new TaskFailedException(new Task(6, TaskStatus::Canceled, 'indexSwap'));

        $this->assertSame('Meilisearch task 6 (indexSwap) canceled: no reason given', $exception->getMessage());
    }
}
