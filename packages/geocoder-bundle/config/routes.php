<?php

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/*
 * Import these routes in the application with:
 *
 *     $routes->import('@Tito10047GeocoderBundle/config/routes.php');
 *
 * See https://symfony.com/doc/current/bundles/best_practices.html#routing
 */
return static function (RoutingConfigurator $routes): void {
    $routes->add('tito10047_geocoder_hello', '/hello/{name}')
        ->controller('tito10047_geocoder.controller.hello')
        ->methods(['GET'])
        ->defaults(['name' => 'World'])
    ;
};
