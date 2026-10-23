<?php

namespace Tito10047\Geocoder\Tests\Import;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Import\SystemClock;

final class SystemClockTest extends TestCase
{
    public function testGivesTheCurrentTimeInUtc(): void
    {
        $before = time();
        $now = (new SystemClock())->now();

        $this->assertSame('UTC', $now->getTimezone()->getName());
        $this->assertGreaterThanOrEqual($before, $now->getTimestamp());
        $this->assertLessThanOrEqual(time(), $now->getTimestamp());
    }
}
