<?php

namespace Tito10047\Geocoder\Tests\Import\Download;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\Import\Download\GraphHopperRegionUrlResolver;

final class GraphHopperRegionUrlResolverTest extends TestCase
{
    public function testResolvesARegion(): void
    {
        $resolver = new GraphHopperRegionUrlResolver();

        $this->assertSame('https://download1.graphhopper.com/public/europe/slovakia/photon-dump-slovakia-1.0-latest.jsonl.zst', $resolver->url('europe/slovakia'));
        $this->assertSame('slovakia.jsonl.zst', $resolver->fileName('europe/slovakia'));
    }

    public function testResolvesANestedRegion(): void
    {
        $resolver = new GraphHopperRegionUrlResolver('http://mirror.local/dumps', '2.1');

        $this->assertSame('http://mirror.local/dumps/europe/germany/bayern/photon-dump-bayern-2.1-latest.jsonl.zst', $resolver->url('europe/germany/bayern'));
        $this->assertSame('bayern.jsonl.zst', $resolver->fileName('europe/germany/bayern'));
        $this->assertSame('andorra.jsonl.zst', $resolver->fileName('andorra'));
        $this->assertSame('bosnia-herzegovina.jsonl.zst', $resolver->fileName('europe/bosnia-herzegovina'));
    }

    #[DataProvider('provideInvalidRegions')]
    public function testRejectsAnInvalidRegion(string $region): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new GraphHopperRegionUrlResolver())->url($region);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidRegions(): iterable
    {
        yield 'empty' => [''];
        yield 'parent directory' => ['europe/../etc'];
        yield 'leading slash' => ['/europe/slovakia'];
        yield 'trailing slash' => ['europe/slovakia/'];
        yield 'upper case' => ['Europe/Slovakia'];
        yield 'query' => ['europe/slovakia?x=1'];
        yield 'space' => ['europe/slo vakia'];
    }

    #[DataProvider('provideInvalidArguments')]
    public function testRejectsInvalidArguments(string $baseUrl, string $version): void
    {
        $this->expectException(InvalidArgumentException::class);

        new GraphHopperRegionUrlResolver($baseUrl, $version);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideInvalidArguments(): iterable
    {
        yield 'not a URL' => ['download1.graphhopper.com/public', '1.0'];
        yield 'ftp' => ['ftp://example.com/public', '1.0'];
        yield 'query' => ['https://example.com/public?x=1', '1.0'];
        yield 'empty version' => ['https://example.com/public', ''];
        yield 'version with a slash' => ['https://example.com/public', '1/0'];
    }
}
