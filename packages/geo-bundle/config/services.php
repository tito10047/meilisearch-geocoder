<?php

use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Services are defined explicitly: no autowiring, no autoconfiguration, private by default,
 * with ids prefixed by "tito10047_geo." and aliases for autowiring.
 *
 * The services shared by the sections; those of each section are in services/, loaded when the
 * section is enabled.
 *
 * See https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $container->services()
        // the PSR-17 request, stream and response factories of the PSR-18 clients
        ->set('tito10047_geo.psr17_factory', Psr17Factory::class)
    ;
};
