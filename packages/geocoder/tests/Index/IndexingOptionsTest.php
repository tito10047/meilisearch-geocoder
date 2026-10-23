<?php

namespace Tito10047\Geocoder\Tests\Index;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\IndexingOptions;

final class IndexingOptionsTest extends TestCase
{
    public function testHasDefaults(): void
    {
        $options = new IndexingOptions();

        $this->assertSame(10_000, $options->batchSize);
        $this->assertSame(2, $options->maxPendingTasks);
        $this->assertEqualsWithDelta(3600.0, $options->taskTimeout, \PHP_FLOAT_EPSILON);
        $this->assertEqualsWithDelta(0.1, $options->pollInterval, \PHP_FLOAT_EPSILON);
    }

    public function testAcceptsNoPollInterval(): void
    {
        $this->assertEqualsWithDelta(0.0, (new IndexingOptions(pollInterval: 0.0))->pollInterval, \PHP_FLOAT_EPSILON);
    }

    /**
     * @param \Closure(): IndexingOptions $create
     */
    #[DataProvider('provideInvalidOptions')]
    public function testRejectsInvalidOptions(\Closure $create): void
    {
        $this->expectException(InvalidArgumentException::class);

        $create();
    }

    /**
     * @return iterable<string, array{\Closure(): IndexingOptions}>
     */
    public static function provideInvalidOptions(): iterable
    {
        yield 'no batch' => [static fn (): IndexingOptions => new IndexingOptions(batchSize: 0)];
        yield 'no pending task' => [static fn (): IndexingOptions => new IndexingOptions(maxPendingTasks: 0)];
        yield 'no timeout' => [static fn (): IndexingOptions => new IndexingOptions(taskTimeout: 0.0)];
        yield 'infinite timeout' => [static fn (): IndexingOptions => new IndexingOptions(taskTimeout: \INF)];
        yield 'NaN timeout' => [static fn (): IndexingOptions => new IndexingOptions(taskTimeout: \NAN)];
        yield 'negative poll interval' => [static fn (): IndexingOptions => new IndexingOptions(pollInterval: -0.1)];
        yield 'poll interval over the timeout' => [static fn (): IndexingOptions => new IndexingOptions(taskTimeout: 1.0, pollInterval: 2.0)];
    }
}
