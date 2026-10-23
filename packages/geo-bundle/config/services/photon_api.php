<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\GeoBundle\Photon\PhotonApiController;
use Tito10047\Geocoder\Photon\FeatureFactory;
use Tito10047\Geocoder\Photon\FeatureFactoryInterface;
use Tito10047\Geocoder\Photon\PhotonSearch;
use Tito10047\Geocoder\Photon\PhotonSearchInterface;
use Tito10047\Geocoder\Search\SearchOptions;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The Photon API, loaded when "geo.geocoder.photon_api" is enabled. Its route comes
 * from the route loader of services.php (config/routes.php).
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('geo.geocoder.photon_api.feature_factory', FeatureFactory::class)
            ->args([service('geo.geocoder.name_catalog'), service('geo.geocoder.selection_rules')])
        ->alias(FeatureFactoryInterface::class, 'geo.geocoder.photon_api.feature_factory')

        // the configured defaults, with the partial matches a Photon client expects
        ->set('geo.geocoder.photon_api.search_options', SearchOptions::class)
            ->factory([service('geo.geocoder.search_options'), 'withCompleteMatchesOnly'])
            ->args([false])

        ->set('geo.geocoder.photon_api.search', PhotonSearch::class)
            ->args([
                service('geo.geocoder'),
                service('geo.geocoder.photon_api.feature_factory'),
                service('geo.geocoder.photon_api.search_options'),
            ])
        ->alias(PhotonSearchInterface::class, 'geo.geocoder.photon_api.search')

        // public, as controllers are: the controller resolver gets it from the container
        ->set('geo.geocoder.photon_api.controller', PhotonApiController::class)
            ->public()
            ->args([service('geo.geocoder.photon_api.search'), abstract_arg('max age')])
            ->tag('controller.service_arguments')
    ;
};
