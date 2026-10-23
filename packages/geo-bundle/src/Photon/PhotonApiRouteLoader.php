<?php

namespace Tito10047\GeoBundle\Photon;

use Symfony\Component\DependencyInjection\Config\ContainerParametersResource;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * The routes of config/routes.php: "GET /api" of the Photon API while it is enabled, none otherwise.
 *
 * A route loader rather than a static route, so that the application imports the file once and
 * the configuration decides: a static route to a controller that is not registered would fail at
 * the first request. The collection depends on the parameter of the configuration, so a cached
 * router is rebuilt when the API is switched on or off.
 */
final readonly class PhotonApiRouteLoader
{
    public const ROUTE = 'tito10047_geo_photon_api';

    public const ENABLED_PARAMETER = 'tito10047_geo.geocoder.photon_api.enabled';

    /**
     * @param bool   $enabled    whether the Photon API is enabled
     * @param string $controller the id of the service of its controller
     */
    public function __construct(
        private bool $enabled,
        private string $controller,
    ) {
    }

    public function __invoke(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->addResource(new ContainerParametersResource([self::ENABLED_PARAMETER => $this->enabled]));
        if ($this->enabled) {
            $routes->add(self::ROUTE, new Route('/api', ['_controller' => $this->controller], methods: ['GET', 'HEAD']));
        }

        return $routes;
    }
}
