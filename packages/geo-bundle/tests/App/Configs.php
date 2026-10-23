<?php

namespace Tito10047\GeoBundle\Tests\App;

use Tito10047\GeoBundle\Tests\Fake\FakeGeocoder;
use Tito10047\GeoBundle\Tests\Fake\FakeIndexManager;
use Tito10047\GeoBundle\Tests\Fake\FakeRouter;
use Tito10047\GeoBundle\Tests\Fake\FakeRoutingStatusProvider;

/**
 * Configurations of the bundle shared by the tests.
 */
final class Configs
{
    /**
     * A DSN that fails as soon as it is connected to: the directory does not exist.
     */
    public const UNREACHABLE_DSN = 'sqlite:/nonexistent-tito10047-geo/geo.sqlite';

    /**
     * The fakes of the services the commands and the autocompleter use.
     *
     * @var array<string, class-string>
     */
    public const FAKES = [
        'tito10047_geo.geocoder' => FakeGeocoder::class,
        'tito10047_geo.geocoder.index_manager' => FakeIndexManager::class,
        'tito10047_geo.routing.router' => FakeRouter::class,
        'tito10047_geo.routing.status_provider' => FakeRoutingStatusProvider::class,
    ];

    private function __construct()
    {
    }

    /**
     * @param array<string, mixed> $geocoder merged into the minimal configuration of the geocoder
     *
     * @return array<string, mixed>
     */
    public static function geocoder(array $geocoder = []): array
    {
        return ['geocoder' => array_replace([
            'meilisearch' => ['url' => 'http://127.0.0.1:7700', 'api_key' => 'key'],
            'database' => ['dsn' => self::UNREACHABLE_DSN],
        ], $geocoder)];
    }

    /**
     * @param array<string, mixed> $routing merged into the minimal configuration of the routing
     *
     * @return array<string, mixed>
     */
    public static function routing(array $routing = []): array
    {
        return ['routing' => array_replace(['url' => 'http://127.0.0.1:8002'], $routing)];
    }

    /**
     * @return array<string, mixed>
     */
    public static function both(): array
    {
        return self::geocoder() + self::routing();
    }
}
