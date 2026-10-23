<?php

namespace Tito10047\Geocoder\Tests\Index;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Index\TaskStatus;

final class TaskStatusTest extends TestCase
{
    #[DataProvider('provideStatuses')]
    public function testIsFinished(TaskStatus $status, bool $finished): void
    {
        $this->assertSame($finished, $status->isFinished());
    }

    /**
     * @return iterable<string, array{TaskStatus, bool}>
     */
    public static function provideStatuses(): iterable
    {
        yield 'enqueued' => [TaskStatus::Enqueued, false];
        yield 'processing' => [TaskStatus::Processing, false];
        yield 'succeeded' => [TaskStatus::Succeeded, true];
        yield 'failed' => [TaskStatus::Failed, true];
        yield 'canceled' => [TaskStatus::Canceled, true];
    }
}
