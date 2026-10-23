<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/*
 * Services are defined explicitly: no autowiring, no autoconfiguration, private by default,
 * with ids prefixed by "tito10047_geo." and aliases for autowiring.
 *
 * See https://symfony.com/doc/current/bundles/best_practices.html#services
 */
return static function (ContainerConfigurator $container): void {
    $container->services();
};
