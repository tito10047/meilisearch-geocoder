<?php

namespace Tito10047\GeoBundle\Geocoder;

use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Search\AddressLookupInterface;
use Tito10047\Geocoder\Search\Backend\DocumentSourceInterface;
use Tito10047\Geocoder\Search\Backend\SearchBackendInterface;
use Tito10047\Geocoder\Search\GeocoderInterface;

/**
 * Creates the services of the geocoder that need Meilisearch from its URL as resolved at runtime:
 * the real implementation when it is given, UnconfiguredMeilisearch when it is empty.
 *
 * The URL is usually an environment variable, left empty where there is no search engine. Only the
 * URL mode goes through this factory: an existing Meilisearch\Client service ("client") is used as
 * it is.
 */
final class GeocoderServiceFactory
{
    /**
     * @param string|null                   $url        the URL of Meilisearch; empty or null when it is not configured
     * @param \Closure(): GeocoderInterface $configured creates the geocoder searching Meilisearch
     */
    public static function geocoder(?string $url, \Closure $configured): GeocoderInterface
    {
        return self::isConfigured($url) ? $configured() : new UnconfiguredMeilisearch();
    }

    /**
     * @param string|null                        $url        the URL of Meilisearch; empty or null when it is not configured
     * @param \Closure(): AddressLookupInterface $configured creates the lookup reading Meilisearch
     */
    public static function addressLookup(?string $url, \Closure $configured): AddressLookupInterface
    {
        return self::isConfigured($url) ? $configured() : new UnconfiguredMeilisearch();
    }

    /**
     * @param string|null                                                  $url        the URL of Meilisearch; empty or null when it is not configured
     * @param \Closure(): (SearchBackendInterface&DocumentSourceInterface) $configured creates the Meilisearch backend
     */
    public static function searchBackend(?string $url, \Closure $configured): SearchBackendInterface&DocumentSourceInterface
    {
        return self::isConfigured($url) ? $configured() : new UnconfiguredMeilisearch();
    }

    /**
     * @param string|null                       $url        the URL of Meilisearch; empty or null when it is not configured
     * @param \Closure(): IndexManagerInterface $configured creates the Meilisearch index manager
     */
    public static function indexManager(?string $url, \Closure $configured): IndexManagerInterface
    {
        return self::isConfigured($url) ? $configured() : new UnconfiguredMeilisearch();
    }

    private static function isConfigured(?string $url): bool
    {
        return null !== $url && '' !== $url;
    }
}
