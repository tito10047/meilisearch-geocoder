<?php

namespace Tito10047\Geocoder\Tests\Fake;

use Tito10047\Geocoder\Import\Download\RegionUrlResolverInterface;

/**
 * Resolves every region to an uncompressed dump on example.com.
 */
final readonly class PlainRegionUrlResolver implements RegionUrlResolverInterface
{
    public function url(string $region): string
    {
        return 'https://example.com/'.$region.'.jsonl';
    }

    public function fileName(string $region): string
    {
        return str_replace('/', '-', $region).'.jsonl';
    }
}
