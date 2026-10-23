<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * The routes of the bundle, imported by the application under a prefix of its choice:
 *
 *     # config/routes/tito10047_geo.yaml
 *     tito10047_geo:
 *         resource: '@Tito10047GeoBundle/config/routes.php'
 *         prefix: /photon
 *
 * "GET /photon/api?q=…": the Photon API, when "tito10047_geo.geocoder.photon_api" is enabled.
 * The routes come from a route loader of the bundle, which adds none while the API is disabled,
 * so the import may stay in place whatever the configuration.
 */
return static function (RoutingConfigurator $routes): void {
    $routes->import('tito10047_geo.photon_api.route_loader', 'service');
};
