<?php

namespace Tito10047\GeoBundle\Tests\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tito10047\GeoBundle\Routing\RoutingServiceFactory;
use Tito10047\GeoBundle\Routing\UnconfiguredValhalla;
use Tito10047\GeoBundle\Tests\Fake\FakeMatrix;
use Tito10047\GeoBundle\Tests\Fake\FakeRouter;
use Tito10047\GeoBundle\Tests\Fake\FakeRoutingStatusProvider;

final class RoutingServiceFactoryTest extends TestCase
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
        $never = static fn (): never => throw new \LogicException('The Valhalla service was created.');

        $this->assertInstanceOf(UnconfiguredValhalla::class, RoutingServiceFactory::router($url, $never));
        $this->assertInstanceOf(UnconfiguredValhalla::class, RoutingServiceFactory::matrix($url, $never));
        $this->assertInstanceOf(UnconfiguredValhalla::class, RoutingServiceFactory::statusProvider($url, $never));
    }

    public function testWithUrlTheValhallaServicesAreCreated(): void
    {
        $router = new FakeRouter();
        $matrix = new FakeMatrix();
        $statusProvider = new FakeRoutingStatusProvider();

        $this->assertSame($router, RoutingServiceFactory::router('http://127.0.0.1:8002', static fn (): FakeRouter => $router));
        $this->assertSame($matrix, RoutingServiceFactory::matrix('http://127.0.0.1:8002', static fn (): FakeMatrix => $matrix));
        $this->assertSame($statusProvider, RoutingServiceFactory::statusProvider('http://127.0.0.1:8002', static fn (): FakeRoutingStatusProvider => $statusProvider));
    }
}
