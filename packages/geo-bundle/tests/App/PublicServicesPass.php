<?php

namespace Tito10047\GeoBundle\Tests\App;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Makes the services of the bundle and their aliases public in the test application, so that
 * the tests can get the services nothing else uses (a private unused service is removed).
 */
final class PublicServicesPass implements CompilerPassInterface
{
    private const PREFIX = 'tito10047_geo.';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (str_starts_with($id, self::PREFIX) && !$definition->isAbstract()) {
                $definition->setPublic(true);
            }
        }
        foreach ($container->getAliases() as $id => $alias) {
            if (str_starts_with($id, self::PREFIX) || str_starts_with((string) $alias, self::PREFIX)) {
                $alias->setPublic(true);
            }
        }
    }
}
