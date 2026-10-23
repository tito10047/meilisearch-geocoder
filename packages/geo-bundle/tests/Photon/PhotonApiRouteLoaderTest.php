<?php

namespace Tito10047\GeoBundle\Tests\Photon;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\Route;
use Tito10047\GeoBundle\Photon\PhotonApiRouteLoader;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestKernel;

final class PhotonApiRouteLoaderTest extends TestCase
{
    public function testRouteWhenEnabled(): void
    {
        $routes = (new PhotonApiRouteLoader(true, 'app.controller'))();

        $route = $routes->get(PhotonApiRouteLoader::ROUTE);
        $this->assertInstanceOf(Route::class, $route);
        $this->assertSame('/api', $route->getPath());
        $this->assertSame(['GET', 'HEAD'], $route->getMethods());
        $this->assertSame('app.controller', $route->getDefault('_controller'));
        $this->assertEquals([new ContainerParametersResource([PhotonApiRouteLoader::ENABLED_PARAMETER => true])], $routes->getResources());
    }

    public function testNoRouteWhenDisabled(): void
    {
        $routes = (new PhotonApiRouteLoader(false, 'app.controller'))();

        $this->assertCount(0, $routes);
        $this->assertEquals([new ContainerParametersResource([PhotonApiRouteLoader::ENABLED_PARAMETER => false])], $routes->getResources());
    }

    /**
     * The test application imports the routes of the bundle whatever the configuration.
     *
     * @param array<string, mixed> $config
     */
    #[DataProvider('provideDisabledConfigurations')]
    public function testImportedRoutesAreEmptyWhenDisabled(array $config): void
    {
        $kernel = new TestKernel(extensionConfigs: ['geo' => $config], fakes: Configs::FAKES);
        $browser = new KernelBrowser($kernel);

        $browser->request('GET', '/photon/api?q=brat');

        $this->assertSame(404, $browser->getResponse()->getStatusCode());
        $kernel->shutdown();
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideDisabledConfigurations(): iterable
    {
        yield 'by default' => [Configs::geocoder()];
        yield 'explicitly' => [Configs::geocoder(['photon_api' => false])];
        yield 'enabled without the geocoder' => [['geocoder' => ['enabled' => false, 'photon_api' => true]] + Configs::routing()];
        yield 'nothing configured' => [[]];
    }
}
