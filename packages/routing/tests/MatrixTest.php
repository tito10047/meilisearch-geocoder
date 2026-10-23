<?php

namespace Tito10047\Routing\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\Matrix;
use Tito10047\Routing\MatrixEntry;

final class MatrixTest extends TestCase
{
    private Matrix $matrix;

    protected function setUp(): void
    {
        $this->matrix = new Matrix(
            [[57_977, null, 300_000], [0, 1_000, null]],
            [[2_058, null, 2_058], [0, 90, null]],
        );
    }

    public function testReadsThePairs(): void
    {
        $this->assertSame(2, $this->matrix->sourceCount());
        $this->assertSame(3, $this->matrix->targetCount());
        $this->assertSame(57_977, $this->matrix->distanceMeters(0, 0));
        $this->assertSame(2_058, $this->matrix->durationSeconds(0, 0));
        $this->assertSame(0, $this->matrix->distanceMeters(1, 0));
        $this->assertTrue($this->matrix->isReachable(1, 0));
    }

    public function testAnUnreachablePairIsNull(): void
    {
        $this->assertNull($this->matrix->distanceMeters(0, 1));
        $this->assertNull($this->matrix->durationSeconds(0, 1));
        $this->assertFalse($this->matrix->isReachable(0, 1));
    }

    public function testListsEveryPairRowByRow(): void
    {
        $entries = iterator_to_array($this->matrix->entries(), false);

        $this->assertCount(6, $entries);
        $this->assertEquals(new MatrixEntry(0, 0, 57_977, 2_058), $entries[0]);
        $this->assertEquals(new MatrixEntry(0, 1, null, null), $entries[1]);
        $this->assertEquals(new MatrixEntry(1, 1, 1_000, 90), $entries[4]);
        $this->assertSame([true, false, true, true, true, false], array_map(static fn (MatrixEntry $entry): bool => $entry->isReachable(), $entries));
    }

    public function testFindsTheNearestTargetByDuration(): void
    {
        // the first of two equally long durations, unreachable targets skipped
        $this->assertEquals(new MatrixEntry(0, 0, 57_977, 2_058), $this->matrix->nearestTarget(0));
        $this->assertEquals(new MatrixEntry(1, 0, 0, 0), $this->matrix->nearestTarget(1));
    }

    public function testHasNoNearestTargetWhenNoneIsReachable(): void
    {
        $this->assertNull((new Matrix([[null, null]], [[null, null]]))->nearestTarget(0));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function provideIndexesOutside(): iterable
    {
        yield 'negative source' => [-1, 0];
        yield 'source too large' => [2, 0];
        yield 'negative target' => [0, -1];
        yield 'target too large' => [0, 3];
    }

    #[DataProvider('provideIndexesOutside')]
    public function testRejectsAnIndexOutsideTheMatrix(int $source, int $target): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->matrix->distanceMeters($source, $target);
    }

    public function testRejectsANearestTargetOfAnUnknownSource(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->matrix->nearestTarget(5);
    }

    /**
     * @param array<mixed> $distances
     * @param array<mixed> $durations
     */
    #[DataProvider('provideInvalidMatrices')]
    public function testRejectsAnInvalidMatrix(array $distances, array $durations, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        /* @phpstan-ignore argument.type, argument.type */
        new Matrix($distances, $durations);
    }

    /**
     * @return iterable<string, array{array<mixed>, array<mixed>, string}>
     */
    public static function provideInvalidMatrices(): iterable
    {
        yield 'empty' => [[], [], 'non-empty lists of rows'];
        yield 'rows differ in number' => [[[1], [2]], [[1]], 'The distances have 2 row(s), the durations 1'];
        yield 'empty row' => [[[]], [[]], 'The row 0 must be a non-empty list'];
        yield 'ragged rows' => [[[1, 2], [3]], [[1, 2], [3]], 'Every row must have 2 target(s), the row 1 has 1'];
        yield 'durations narrower' => [[[1, 2]], [[1]], 'the row 0 has 2 distance(s) and 1 duration(s)'];
        yield 'negative distance' => [[[-1]], [[1]], 'The distance from 0 to 0 must be null or an integer of 0 or more'];
        yield 'float duration' => [[[1]], [[1.5]], 'The duration from 0 to 0 must be null or an integer of 0 or more, float given'];
        yield 'null distance only' => [[[null]], [[5]], 'must both be null or neither'];
    }
}
