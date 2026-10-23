<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Valhalla\ShapeSpan;

final class ShapeSpanTest extends TestCase
{
    public function testHoldsTheIndexes(): void
    {
        $span = new ShapeSpan(151, 287);

        $this->assertSame(151, $span->beginShapeIndex);
        $this->assertSame(287, $span->endShapeIndex);
    }

    public function testMayBeASinglePoint(): void
    {
        $this->assertSame(0, (new ShapeSpan(0, 0))->endShapeIndex);
    }

    #[DataProvider('provideInvalidSpans')]
    public function testRejectsInvalidIndexes(int $begin, int $end): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ShapeSpan($begin, $end);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function provideInvalidSpans(): iterable
    {
        yield 'negative' => [-1, 3];
        yield 'backwards' => [5, 4];
    }
}
