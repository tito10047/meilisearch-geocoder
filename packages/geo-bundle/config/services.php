<?php

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\GeoBundle\Photon\PhotonApiRouteLoader;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;

/*
 * Services are defined explicitly: no autowiring, no autoconfiguration, private by default,
 * with ids prefixed by "geo." and aliases for autowiring.
 *
 * The services shared by the sections; those of each section are in services/, loaded when the
 * section is enabled.
 *
 * See https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        // the PSR-17 request, stream and response factories of the PSR-18 clients
        ->set('geo.psr17_factory', Psr17Factory::class)

        // the routes of config/routes.php: registered whatever the configuration, so that importing them never fails
        ->set('geo.photon_api.route_loader', PhotonApiRouteLoader::class)
            ->args([param(PhotonApiRouteLoader::ENABLED_PARAMETER), 'geo.geocoder.photon_api.controller'])
            ->tag('routing.route_loader')
    ;
};
