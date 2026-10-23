<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\GeoBundle\Photon\PhotonApiController;
use Tito10047\Geocoder\Photon\FeatureFactory;
use Tito10047\Geocoder\Photon\FeatureFactoryInterface;
use Tito10047\Geocoder\Photon\PhotonSearch;
use Tito10047\Geocoder\Photon\PhotonSearchInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The Photon API, loaded when "tito10047_geo.geocoder.photon_api" is enabled. Its route comes
 * from the route loader of services.php (config/routes.php).
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('tito10047_geo.geocoder.photon_api.feature_factory', FeatureFactory::class)
            ->args([service('tito10047_geo.geocoder.name_catalog'), service('tito10047_geo.geocoder.selection_rules')])
        ->alias(FeatureFactoryInterface::class, 'tito10047_geo.geocoder.photon_api.feature_factory')

        ->set('tito10047_geo.geocoder.photon_api.search', PhotonSearch::class)
            ->args([
                service('tito10047_geo.geocoder'),
                service('tito10047_geo.geocoder.photon_api.feature_factory'),
                service('tito10047_geo.geocoder.search_options'),
            ])
        ->alias(PhotonSearchInterface::class, 'tito10047_geo.geocoder.photon_api.search')

        // public, as controllers are: the controller resolver gets it from the container
        ->set('tito10047_geo.geocoder.photon_api.controller', PhotonApiController::class)
            ->public()
            ->args([service('tito10047_geo.geocoder.photon_api.search'), abstract_arg('max age')])
            ->tag('controller.service_arguments')
    ;
};
