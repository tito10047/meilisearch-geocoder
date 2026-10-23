<?php

namespace Tito10047\GeoBundle\Tests\App;

use Tito10047\GeoBundle\Tests\Fake\FakeArtifactBuilder;
use Tito10047\GeoBundle\Tests\Fake\FakeArtifactInstallerFactory;
use Tito10047\GeoBundle\Tests\Fake\FakeDumpDownloader;
use Tito10047\GeoBundle\Tests\Fake\FakeFileExporter;
use Tito10047\GeoBundle\Tests\Fake\FakeGeocoder;
use Tito10047\GeoBundle\Tests\Fake\FakeImporter;
use Tito10047\GeoBundle\Tests\Fake\FakeIndexManager;
use Tito10047\GeoBundle\Tests\Fake\FakeMatrix;
use Tito10047\GeoBundle\Tests\Fake\FakeRouter;
use Tito10047\GeoBundle\Tests\Fake\FakeRoutingStatusProvider;

/**
 * Configurations of the bundle shared by the tests.
 */
final class Configs
{
    /**
     * A DSN that fails as soon as it is connected to: its directory cannot be created (/dev/null is
     * a file), even by root.
     */
    public const UNREACHABLE_DSN = 'sqlite:/dev/null/tito10047-geo/geo.sqlite';

    /**
     * An environment variable resolving to an empty string (the default of its parameter in
     * TestKernel), as an application leaves VALHALLA_URL or MEILISEARCH_URL where there is no service.
     */
    public const EMPTY_ENV = '%env(GEO_TEST_EMPTY)%';

    /** An environment variable resolving to the index uid "places". */
    public const INDEX_ENV = '%env(GEO_TEST_INDEX)%';

    /**
     * An environment variable without a default: a test sets it at runtime (its value is not
     * known when the container is built, so the configuration cannot validate it).
     */
    public const RUNTIME_INDEX_ENV = '%env(GEO_TEST_RUNTIME_INDEX)%';

    /**
     * The fakes of the services the commands and the autocompleter use.
     *
     * @var array<string, class-string>
     */
    public const FAKES = [
        'geo.geocoder' => FakeGeocoder::class,
        'geo.geocoder.index_manager' => FakeIndexManager::class,
        'geo.geocoder.importer' => FakeImporter::class,
        'geo.geocoder.file_exporter' => FakeFileExporter::class,
        'geo.geocoder.artifact_builder' => FakeArtifactBuilder::class,
        'geo.geocoder.artifact_installer_factory' => FakeArtifactInstallerFactory::class,
        'geo.geocoder.import.dump_downloader' => FakeDumpDownloader::class,
        'geo.routing.router' => FakeRouter::class,
        'geo.routing.matrix' => FakeMatrix::class,
        'geo.routing.status_provider' => FakeRoutingStatusProvider::class,
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
