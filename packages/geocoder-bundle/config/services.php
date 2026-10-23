<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\Geocoder\Greeter;
use Tito10047\Geocoder\GreeterInterface;
use Tito10047\GeocoderBundle\Command\HelloCommand;
use Tito10047\GeocoderBundle\Controller\HelloController;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * Services are defined explicitly: no autowiring, no autoconfiguration, private by default,
 * with ids prefixed by "tito10047_geocoder." and aliases for autowiring.
 *
 * See https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        ->set('tito10047_geocoder.greeter', Greeter::class)

        ->set('tito10047_geocoder.controller.hello', HelloController::class)
            ->args([
                service('tito10047_geocoder.greeter'),
            ])
            ->tag('controller.service_arguments')

        ->set('tito10047_geocoder.command.hello', HelloCommand::class)
            ->args([
                service('tito10047_geocoder.greeter'),
            ])
            ->tag('console.command')

        ->alias(GreeterInterface::class, 'tito10047_geocoder.greeter')
    ;
};
