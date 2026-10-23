<?php

namespace Tito10047\GeoBundle\Routing;

use Tito10047\Routing\MatrixInterface;
use Tito10047\Routing\RouterInterface;

/**
 * Creates the routing services from the URL of Valhalla as resolved at runtime: the Valhalla
 * implementation when it is given, UnconfiguredValhalla when it is empty.
 *
 * The URL is usually an environment variable, left empty where there are no routing data. The
 * Valhalla clients rightly refuse an empty URL in their constructor, which would make every
 * service depending on the routing fail to be created; the choice is therefore made here, before
 * they are created. A URL that is given but invalid still fails at creation: a configuration error.
 */
final class RoutingServiceFactory
{
    /**
     * @param string|null                 $url      the URL of Valhalla; empty or null when it is not configured
     * @param \Closure(): RouterInterface $valhalla creates the Valhalla router
     */
    public static function router(?string $url, \Closure $valhalla): RouterInterface
    {
        return self::isConfigured($url) ? $valhalla() : new UnconfiguredValhalla();
    }

    /**
     * @param string|null                 $url      the URL of Valhalla; empty or null when it is not configured
     * @param \Closure(): MatrixInterface $valhalla creates the Valhalla matrix
     */
    public static function matrix(?string $url, \Closure $valhalla): MatrixInterface
    {
        return self::isConfigured($url) ? $valhalla() : new UnconfiguredValhalla();
    }

    /**
     * @param string|null                                $url      the URL of Valhalla; empty or null when it is not configured
     * @param \Closure(): RoutingStatusProviderInterface $valhalla creates the provider asking Valhalla
     */
    public static function statusProvider(?string $url, \Closure $valhalla): RoutingStatusProviderInterface
    {
        return self::isConfigured($url) ? $valhalla() : new UnconfiguredValhalla();
    }

    private static function isConfigured(?string $url): bool
    {
        return null !== $url && '' !== $url;
    }
}
