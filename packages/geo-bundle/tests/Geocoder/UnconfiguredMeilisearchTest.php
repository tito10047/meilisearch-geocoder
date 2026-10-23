<?php

namespace Tito10047\GeoBundle\Tests\Geocoder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\GeoBundle\Geocoder\UnconfiguredMeilisearch;
use Tito10047\Geocoder\Exception\GeocoderNotConfiguredException;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Search\Backend\BackendQuery;

final class UnconfiguredMeilisearchTest extends TestCase
{
    /**
     * @return iterable<string, array{\Closure(UnconfiguredMeilisearch): mixed}>
     */
    public static function calls(): iterable
    {
        yield 'suggest' => [static fn (UnconfiguredMeilisearch $m): mixed => $m->suggest('Hlavná 12 Košice')];
        yield 'suggest of a short text' => [static fn (UnconfiguredMeilisearch $m): mixed => $m->suggest('K')];
        yield 'geocode' => [static fn (UnconfiguredMeilisearch $m): mixed => $m->geocode('Hlavná 12 Košice')];
        yield 'findByReference' => [static fn (UnconfiguredMeilisearch $m): mixed => $m->findByReference('1234567')];
        yield 'search' => [static fn (UnconfiguredMeilisearch $m): mixed => $m->search(new BackendQuery('Košice', 5))];
        yield 'document' => [static fn (UnconfiguredMeilisearch $m): mixed => $m->document('R109166')];
        yield 'create' => [static function (UnconfiguredMeilisearch $m): void { $m->create('geo', new IndexSettings()); }];
        yield 'applySettings' => [static function (UnconfiguredMeilisearch $m): void { $m->applySettings('geo', new IndexSettings()); }];
        yield 'documentWriter' => [static fn (UnconfiguredMeilisearch $m): mixed => $m->documentWriter('geo')];
        yield 'waitForTasks' => [static function (UnconfiguredMeilisearch $m): void { $m->waitForTasks('geo'); }];
        yield 'compact' => [static function (UnconfiguredMeilisearch $m): void { $m->compact('geo'); }];
        yield 'swap' => [static function (UnconfiguredMeilisearch $m): void { $m->swap('geo_1', 'geo'); }];
        yield 'delete' => [static function (UnconfiguredMeilisearch $m): void { $m->delete('geo'); }];
        yield 'exists' => [static fn (UnconfiguredMeilisearch $m): mixed => $m->exists('geo')];
        yield 'indexUids' => [static fn (UnconfiguredMeilisearch $m): mixed => $m->indexUids()];
        yield 'status' => [static fn (UnconfiguredMeilisearch $m): mixed => $m->status('geo')];
        yield 'snapshot' => [static function (UnconfiguredMeilisearch $m): void { $m->snapshot(); }];
    }

    /**
     * @param \Closure(UnconfiguredMeilisearch): mixed $call
     */
    #[DataProvider('calls')]
    public function testEveryCallIsUnavailable(\Closure $call): void
    {
        $this->expectException(GeocoderNotConfiguredException::class);
        $this->expectExceptionMessage('Meilisearch is not configured: geo.geocoder.meilisearch.url is empty.');

        $call(new UnconfiguredMeilisearch());
    }
}
