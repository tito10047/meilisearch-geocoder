<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\GeoBundle\Autocomplete\GeocoderAutocompleter;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The autocompleter of symfony/ux-autocomplete, loaded when the geocoder is enabled and the
 * integration too (by default when the package is installed). It is tagged with its alias from the
 * configuration by Tito10047GeoBundle::loadExtension().
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('geo.geocoder.autocompleter', GeocoderAutocompleter::class)
            ->args([
                service('geo.geocoder'),
                service('request_stack'),
                service('geo.geocoder.search_options'),
            ])
    ;
};
