<?php

namespace Tito10047\Routing\Tests\Valhalla;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Matrix;
use Tito10047\Routing\MatrixRequest;
use Tito10047\Routing\Tests\Fixtures\Fixture;
use Tito10047\Routing\Valhalla\MatrixResponseParser;

final class MatrixResponseParserTest extends TestCase
{
    public function testReadsARecordedMatrix(): void
    {
        $matrix = $this->parse('matrix-sk-2x3');

        $this->assertSame(2, $matrix->sourceCount());
        $this->assertSame(3, $matrix->targetCount());
        // Bratislava → Žilina, Banská Bystrica, Nitra; Trnava → the same
        $this->assertSame([202_335, 214_558, 92_790, 150_949, 170_637, 48_870], $this->column($matrix, 'distanceMeters'));
        $this->assertSame([6_142, 6_532, 2_978, 4_934, 5_304, 1_749], $this->column($matrix, 'durationSeconds'));
    }

    public function testReadsUnreachablePairsAsNull(): void
    {
        // Vienna snaps to a stray road of the Slovak tiles, Košice is farther than 400 km by road
        $matrix = $this->parse('matrix-unreachable');

        $this->assertSame([57_977, null, null], $this->column($matrix, 'distanceMeters'));
        $this->assertSame([2_058, null, null], $this->column($matrix, 'durationSeconds'));
    }

    public function testReadsAZeroDistanceAcrossBorders(): void
    {
        $matrix = $this->parse('matrix-cross-border-all');

        $this->assertSame([65_685, 135_904, 127_982, 0], $this->column($matrix, 'distanceMeters'));
        $this->assertSame(0, $matrix->durationSeconds(1, 1));
    }

    public function testConvertsMiles(): void
    {
        $request = new MatrixRequest(Fixture::points(Fixture::BRATISLAVA), Fixture::points(Fixture::TRNAVA));

        $matrix = (new MatrixResponseParser())->parse('{"sources_to_targets":{"durations":[[2058.4]],"distances":[[36.025]]},"units":"miles"}', $request);

        $this->assertSame(57_977, $matrix->distanceMeters(0, 0));
        $this->assertSame(2_058, $matrix->durationSeconds(0, 0));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideUnexpectedAnswers(): iterable
    {
        yield 'invalid JSON' => ['<html>', 'invalid JSON'];
        yield 'error object' => ['{"error_code":154}', 'An object with "sources_to_targets" was expected'];
        yield 'verbose answer' => ['{"sources_to_targets":[[{"from_index":0,"to_index":0,"time":1,"distance":1.0}]],"units":"kilometers"}', 'A list of 1 row(s) was expected at sources_to_targets.distances'];
        yield 'other units' => ['{"sources_to_targets":{"durations":[[1]],"distances":[[1.0]]},"units":"furlongs"}', 'The units "furlongs" are not supported'];
        yield 'too many rows' => ['{"sources_to_targets":{"durations":[[1],[1]],"distances":[[1.0],[1.0]]}}', 'A list of 1 row(s)'];
        yield 'too few targets' => ['{"sources_to_targets":{"durations":[[]],"distances":[[]]}}', 'A list of 1 value(s) was expected at sources_to_targets.distances[0]'];
        yield 'string distance' => ['{"sources_to_targets":{"durations":[[1]],"distances":[["1"]]}}', 'A number of 0 or more or null was expected at sources_to_targets.distances[0], string given'];
        yield 'negative duration' => ['{"sources_to_targets":{"durations":[[-1]],"distances":[[1.0]]}}', 'sources_to_targets.durations[0], int given'];
        yield 'null distance only' => ['{"sources_to_targets":{"durations":[[1]],"distances":[[null]]}}', 'must both be null or neither'];
    }

    #[DataProvider('provideUnexpectedAnswers')]
    public function testRejectsAnUnexpectedAnswer(string $body, string $message): void
    {
        $request = new MatrixRequest(Fixture::points(Fixture::BRATISLAVA), Fixture::points(Fixture::TRNAVA));

        $this->expectException(RoutingUnavailableException::class);
        $this->expectExceptionMessage($message);

        (new MatrixResponseParser())->parse($body, $request);
    }

    private function parse(string $name): Matrix
    {
        return (new MatrixResponseParser())->parse(Fixture::responseBody($name), Fixture::matrixRequest($name));
    }

    /**
     * @param 'distanceMeters'|'durationSeconds' $property
     *
     * @return list<int|null> every pair, row by row
     */
    private function column(Matrix $matrix, string $property): array
    {
        $values = [];
        foreach ($matrix->entries() as $entry) {
            $values[] = $entry->{$property};
        }

        return $values;
    }
}
