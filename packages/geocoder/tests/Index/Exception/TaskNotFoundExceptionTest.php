<?php

namespace Tito10047\Geocoder\Tests\Index\Exception;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Index\Exception\TaskNotFoundException;

final class TaskNotFoundExceptionTest extends TestCase
{
    public function testTellsWhyTheTaskMayBeMissing(): void
    {
        $exception = new TaskNotFoundException(7);

        $this->assertSame(7, $exception->taskUid);
        $this->assertStringStartsWith('Meilisearch does not know the task 7: it was deleted, or the API key may not read it', $exception->getMessage());
    }
}
