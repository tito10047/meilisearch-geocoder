<?php

namespace Tito10047\Geocoder\Tests\Index;

use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\IndexStatus;

final class IndexStatusTest extends TestCase
{
    public function testHoldsTheState(): void
    {
        $status = new IndexStatus('geo', 61_754, 17_000_000, 90_000_000, false, null, '1.35.1');

        $this->assertSame('geo', $status->uid);
        $this->assertSame(61_754, $status->documents);
        $this->assertNull($status->settingsHash);
    }

    public function testRejectsNegativeCounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new IndexStatus('geo', -1, 0, 0, false, null, '1.35.1');
    }
}
