<?php

namespace Tito10047\Geocoder\Tests\Index\Exception;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Index\Exception\TaskTimeoutException;

final class TaskTimeoutExceptionTest extends TestCase
{
    public function testListsTheUnfinishedTasks(): void
    {
        $exception = new TaskTimeoutException([3, 4], 1.5);

        $this->assertSame([3, 4], $exception->taskUids);
        $this->assertSame('Meilisearch tasks 3, 4 did not finish within 1.5 s.', $exception->getMessage());
    }
}
