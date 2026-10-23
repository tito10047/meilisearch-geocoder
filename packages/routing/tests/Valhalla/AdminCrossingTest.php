<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Valhalla\AdminCrossing;
use Tito10047\Routing\Valhalla\ShapeSpan;

final class AdminCrossingTest extends TestCase
{
    public function testHoldsTheAdminsAndTheEdge(): void
    {
        $edge = new ShapeSpan(287, 288);
        $crossing = new AdminCrossing(0, 1, $edge);

        $this->assertSame(0, $crossing->fromAdminIndex);
        $this->assertSame(1, $crossing->toAdminIndex);
        $this->assertSame($edge, $crossing->edge);
    }

    #[DataProvider('provideInvalidAdmins')]
    public function testRejectsANegativeAdminIndex(int $from, int $to): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AdminCrossing($from, $to, new ShapeSpan(0, 1));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function provideInvalidAdmins(): iterable
    {
        yield 'from' => [-1, 1];
        yield 'to' => [0, -1];
    }
}
