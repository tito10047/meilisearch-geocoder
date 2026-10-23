<?php

namespace Tito10047\Routing\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Costing;
use Tito10047\Routing\Exception\InvalidArgumentException;
use Tito10047\Routing\MatrixRequest;

final class MatrixRequestTest extends TestCase
{
    public function testHasTheDefaultsOfARouteRequest(): void
    {
        $request = new MatrixRequest([new Coordinates(48.1, 17.1)], [new Coordinates(48.2, 17.2), new Coordinates(48.3, 17.3)]);

        $this->assertSame(Costing::Car, $request->costing);
        $this->assertSame(200, $request->radiusMeters);
        $this->assertFalse($request->avoidTolls);
        $this->assertFalse($request->avoidHighways);
        $this->assertSame(2, $request->pairs());
    }

    public function testAcceptsTheSamePointAsSourceAndTarget(): void
    {
        $point = new Coordinates(48.1, 17.1);

        $this->assertSame(1, (new MatrixRequest([$point], [$point], radiusMeters: 0))->pairs());
    }

    /**
     * @param array<mixed> $sources
     * @param array<mixed> $targets
     */
    #[DataProvider('provideInvalidRequests')]
    public function testRejectsAnInvalidRequest(array $sources, array $targets, int $radius, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        /* @phpstan-ignore argument.type, argument.type */
        new MatrixRequest($sources, $targets, radiusMeters: $radius);
    }

    /**
     * @return iterable<string, array{array<mixed>, array<mixed>, int, string}>
     */
    public static function provideInvalidRequests(): iterable
    {
        $point = new Coordinates(48.1, 17.1);

        yield 'no source' => [[], [$point], 200, 'at least one source'];
        yield 'no target' => [[$point], [], 200, 'at least one target'];
        yield 'sources not a list' => [['a' => $point], [$point], 200, 'The sources must be a list'];
        yield 'target not coordinates' => [[$point], [$point, [48.2, 17.2]], 200, 'The target 1 must be an instance of'];
        yield 'negative radius' => [[$point], [$point], -1, 'must not be negative'];
    }
}
