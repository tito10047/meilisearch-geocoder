<?php

namespace Tito10047\GeoBundle\Tests\Geocoder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\GeoBundle\Geocoder\GeocoderServiceFactory;
use Tito10047\GeoBundle\Geocoder\UnconfiguredMeilisearch;
use Tito10047\GeoBundle\Tests\Fake\FakeGeocoder;
use Tito10047\GeoBundle\Tests\Fake\FakeIndexManager;

final class GeocoderServiceFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null}>
     */
    public static function unconfiguredUrls(): iterable
    {
        yield 'empty' => [''];
        yield 'null' => [null];
    }

    #[DataProvider('unconfiguredUrls')]
    public function testWithoutUrlTheServicesAreUnconfigured(?string $url): void
    {
        $never = static fn (): never => throw new \LogicException('The configured service was created.');

        $this->assertInstanceOf(UnconfiguredMeilisearch::class, GeocoderServiceFactory::geocoder($url, $never));
        $this->assertInstanceOf(UnconfiguredMeilisearch::class, GeocoderServiceFactory::addressLookup($url, $never));
        $this->assertInstanceOf(UnconfiguredMeilisearch::class, GeocoderServiceFactory::searchBackend($url, $never));
        $this->assertInstanceOf(UnconfiguredMeilisearch::class, GeocoderServiceFactory::indexManager($url, $never));
    }

    public function testWithUrlTheConfiguredServicesAreCreated(): void
    {
        $geocoder = new FakeGeocoder();
        $indexes = new FakeIndexManager();
        // any implementation stands for the configured one: only its identity is checked
        $backend = new UnconfiguredMeilisearch();

        $this->assertSame($geocoder, GeocoderServiceFactory::geocoder('http://127.0.0.1:7700', static fn (): FakeGeocoder => $geocoder));
        $this->assertSame($backend, GeocoderServiceFactory::addressLookup('http://127.0.0.1:7700', static fn (): UnconfiguredMeilisearch => $backend));
        $this->assertSame($backend, GeocoderServiceFactory::searchBackend('http://127.0.0.1:7700', static fn (): UnconfiguredMeilisearch => $backend));
        $this->assertSame($indexes, GeocoderServiceFactory::indexManager('http://127.0.0.1:7700', static fn (): FakeIndexManager => $indexes));
    }
}
