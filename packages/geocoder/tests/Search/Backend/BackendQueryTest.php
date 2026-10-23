<?php

namespace Tito10047\Geocoder\Tests\Search\Backend;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Document\DocumentKind;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Search\Backend\BackendQuery;

final class BackendQueryTest extends TestCase
{
    public function testKeepsTheValues(): void
    {
        $query = new BackendQuery('Hlavná Košice', 15, ['SK', 'CZ'], [DocumentKind::Street]);

        $this->assertSame('Hlavná Košice', $query->text);
        $this->assertSame(15, $query->limit);
        $this->assertSame(['SK', 'CZ'], $query->countries);
        $this->assertSame([DocumentKind::Street], $query->kinds);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    #[DataProvider('provideInvalidArguments')]
    public function testRejectsInvalidArguments(array $arguments): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BackendQuery(...$arguments);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideInvalidArguments(): iterable
    {
        yield 'blank text' => [['text' => ' ', 'limit' => 5]];
        yield 'zero limit' => [['text' => 'a', 'limit' => 0]];
        yield 'lower case country' => [['text' => 'a', 'limit' => 5, 'countries' => ['sk']]];
        yield 'country not a string' => [['text' => 'a', 'limit' => 5, 'countries' => [42]]];
        yield 'countries not a list' => [['text' => 'a', 'limit' => 5, 'countries' => ['x' => 'SK']]];
        yield 'kind not an enum' => [['text' => 'a', 'limit' => 5, 'kinds' => ['street']]];
        yield 'kinds not a list' => [['text' => 'a', 'limit' => 5, 'kinds' => ['x' => DocumentKind::Street]]];
    }
}
