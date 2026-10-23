<?php

namespace Tito10047\Geocoder\Tests\Index;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Index\IndexPublisher;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Tests\Index\Fixtures\InMemoryIndexManager;

final class IndexPublisherTest extends TestCase
{
    public function testFirstPublicationRenamesTheBuild(): void
    {
        $indexes = new InMemoryIndexManager(['geo_20261023120000' => 'new']);

        (new IndexPublisher($indexes, new TimestampIndexNaming()))->publish('geo_20261023120000');

        $this->assertSame(['geo' => 'new'], $indexes->indexes);
        $this->assertSame(['swap geo_20261023120000 geo'], $indexes->log);
    }

    public function testPublicationSwapsAndDeletesTheReplacedIndex(): void
    {
        $indexes = new InMemoryIndexManager(['geo' => 'old', 'geo_20261023120000' => 'new', 'products' => 'other']);

        (new IndexPublisher($indexes, new TimestampIndexNaming()))->publish('geo_20261023120000');

        $this->assertSame(['geo' => 'new', 'products' => 'other'], $indexes->indexes);
        $this->assertSame(['swap geo_20261023120000 geo', 'delete geo_20261023120000'], $indexes->log);
    }

    public function testSwapInLeavesTheReplacedIndexUnderTheBuildUid(): void
    {
        $indexes = new InMemoryIndexManager(['geo' => 'old', 'geo_20261023120000' => 'new']);

        (new IndexPublisher($indexes, new TimestampIndexNaming()))->swapIn('geo_20261023120000');

        $this->assertSame(['geo' => 'new', 'geo_20261023120000' => 'old'], $indexes->indexes);
    }

    public function testSwapBackUndoesASwap(): void
    {
        $indexes = new InMemoryIndexManager(['geo' => 'old', 'geo_20261023120000' => 'new']);
        $publisher = new IndexPublisher($indexes, new TimestampIndexNaming());

        $publisher->swapIn('geo_20261023120000');
        $publisher->swapBack('geo_20261023120000');

        $this->assertSame(['geo' => 'old', 'geo_20261023120000' => 'new'], $indexes->indexes);
    }

    public function testSwapBackUndoesARename(): void
    {
        $indexes = new InMemoryIndexManager(['geo_20261023120000' => 'new']);
        $publisher = new IndexPublisher($indexes, new TimestampIndexNaming());

        $publisher->swapIn('geo_20261023120000');
        $publisher->swapBack('geo_20261023120000');

        $this->assertSame(['geo_20261023120000' => 'new'], $indexes->indexes);
        $this->assertSame(['swap geo_20261023120000 geo', 'swap geo geo_20261023120000'], $indexes->log);
    }

    public function testRemoveReplacedIsDoneWhenNothingWasReplaced(): void
    {
        $indexes = new InMemoryIndexManager(['geo' => 'new']);

        (new IndexPublisher($indexes, new TimestampIndexNaming()))->removeReplaced('geo_20261023120000');

        $this->assertSame(['geo' => 'new'], $indexes->indexes);
        $this->assertSame([], $indexes->log);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function steps(): iterable
    {
        yield 'swap in' => ['swapIn'];
        yield 'swap back' => ['swapBack'];
        yield 'remove the replaced index' => ['removeReplaced'];
    }

    #[DataProvider('steps')]
    public function testEveryStepRefusesAnIndexThatIsNotABuild(string $step): void
    {
        $indexes = new InMemoryIndexManager(['geo' => 'live', 'products' => 'other']);

        try {
            (new IndexPublisher($indexes, new TimestampIndexNaming()))->{$step}('products');
            $this->fail('An index that is not a build was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], $indexes->log);
        }
    }

    public function testRefusesToPublishAnIndexThatIsNotABuild(): void
    {
        $indexes = new InMemoryIndexManager(['products' => 'other']);

        $this->expectException(InvalidArgumentException::class);

        (new IndexPublisher($indexes, new TimestampIndexNaming()))->publish('products');
    }

    public function testCleanupDeletesOnlyBuildsNotKept(): void
    {
        $indexes = new InMemoryIndexManager([
            'geo' => 'live',
            'geo_20261020120000' => 'failed import',
            'geo_20261023120000' => 'running import',
            'geo_products' => 'other',
            'products' => 'other',
        ]);

        $deleted = (new IndexPublisher($indexes, new TimestampIndexNaming()))->cleanup(['geo_20261023120000']);

        $this->assertSame(['geo_20261020120000'], $deleted);
        $this->assertSame(['geo', 'geo_20261023120000', 'geo_products', 'products'], array_keys($indexes->indexes));
    }
}
