<?php

namespace Tito10047\GeoBundle\Tests\Integration;

use Meilisearch\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Client\ClientInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\UX\Autocomplete\AutocompleterRegistry;
use Tito10047\GeoBundle\Artifact\ArtifactInstallerFactory;
use Tito10047\GeoBundle\Artifact\ArtifactInstallerFactoryInterface;
use Tito10047\GeoBundle\Autocomplete\GeocoderAutocompleter;
use Tito10047\GeoBundle\Geocoder\UnconfiguredMeilisearch;
use Tito10047\GeoBundle\Photon\PhotonApiController;
use Tito10047\GeoBundle\Photon\PhotonApiRouteLoader;
use Tito10047\GeoBundle\Routing\RoutingStatusProviderInterface;
use Tito10047\GeoBundle\Routing\UnconfiguredValhalla;
use Tito10047\GeoBundle\Routing\ValhallaStatusProvider;
use Tito10047\GeoBundle\Tests\App\Configs;
use Tito10047\GeoBundle\Tests\App\TestKernel;
use Tito10047\Geocoder\Artifact\ArtifactBuilderInterface;
use Tito10047\Geocoder\Artifact\ArtifactInstallerInterface;
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\HouseNumber\HouseNumberStoreInterface;
use Tito10047\Geocoder\HouseNumber\PdoHouseNumberStore;
use Tito10047\Geocoder\Import\ClockInterface;
use Tito10047\Geocoder\Import\ConverterInterface;
use Tito10047\Geocoder\Import\Download\DumpDownloaderInterface;
use Tito10047\Geocoder\Import\Download\GraphHopperRegionUrlResolver;
use Tito10047\Geocoder\Import\Download\HttpDumpDownloader;
use Tito10047\Geocoder\Import\Download\RegionUrlResolverInterface;
use Tito10047\Geocoder\Import\File\ExportAwareConverter;
use Tito10047\Geocoder\Import\File\FileExporter;
use Tito10047\Geocoder\Import\File\FileExporterInterface;
use Tito10047\Geocoder\Import\Importer;
use Tito10047\Geocoder\Import\ImporterInterface;
use Tito10047\Geocoder\Import\SelectionRules;
use Tito10047\Geocoder\Import\SystemClock;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexNamingInterface;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Index\MeilisearchIndexManager;
use Tito10047\Geocoder\Name\CountryNameSourceInterface;
use Tito10047\Geocoder\Name\NameCatalogInterface;
use Tito10047\Geocoder\Name\NameKind;
use Tito10047\Geocoder\Name\PdoCountryNameSource;
use Tito10047\Geocoder\Photon\FeatureFactory;
use Tito10047\Geocoder\Photon\FeatureFactoryInterface;
use Tito10047\Geocoder\Photon\PhotonSearch;
use Tito10047\Geocoder\Photon\PhotonSearchInterface;
use Tito10047\Geocoder\Search\AddressLookup;
use Tito10047\Geocoder\Search\AddressLookupInterface;
use Tito10047\Geocoder\Search\Backend\DocumentSourceInterface;
use Tito10047\Geocoder\Search\Backend\SearchBackendInterface;
use Tito10047\Geocoder\Search\Geocoder;
use Tito10047\Geocoder\Search\GeocoderInterface;
use Tito10047\Geocoder\Search\LabelFormatterInterface;
use Tito10047\Geocoder\Search\Query\CatalogCountryRecognizer;
use Tito10047\Geocoder\Search\Query\CountryRecognizerInterface;
use Tito10047\Geocoder\Search\Query\QueryParserInterface;
use Tito10047\Geocoder\Search\RerankerInterface;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Text\NameNormalizerInterface;
use Tito10047\Geocoder\Status\GeocoderState;
use Tito10047\Geocoder\Status\GeocoderStatusProvider;
use Tito10047\Geocoder\Status\GeocoderStatusProviderInterface;
use Tito10047\Geocoder\Storage\BuildStorageInterface;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialectInterface;
use Tito10047\Geocoder\Storage\TableNames;
use Tito10047\Routing\Coordinates as RoutingCoordinates;
use Tito10047\Routing\Exception\InvalidArgumentException as RoutingInvalidArgumentException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\Geometry\PolylineDecoderInterface;
use Tito10047\Routing\MatrixInterface;
use Tito10047\Routing\RouteRequest;
use Tito10047\Routing\RouterInterface;
use Tito10047\Routing\Valhalla\CountryLocatorInterface;
use Tito10047\Routing\Valhalla\ErrorTranslatorInterface;
use Tito10047\Routing\Valhalla\MatrixRequestEncoderInterface;
use Tito10047\Routing\Valhalla\MatrixResponseParserInterface;
use Tito10047\Routing\Valhalla\RequestEncoderInterface;
use Tito10047\Routing\Valhalla\ResponseParserInterface;
use Tito10047\Routing\Valhalla\TollLocatorInterface;
use Tito10047\Routing\Valhalla\ValhallaMatrix;
use Tito10047\Routing\Valhalla\ValhallaRouter;

/**
 * Boots the test application with each section of the bundle and checks the services it registers.
 */
final class ContainerTest extends KernelTestCase
{
    private const GEOCODER_COMMANDS = [
        'geo:artifact',
        'geo:artifact:install',
        'geo:cleanup',
        'geo:download',
        'geo:import',
        'geo:publish',
        'geo:search',
        'geo:status',
    ];
    private const ROUTING_COMMANDS = ['geo:matrix', 'geo:route', 'geo:route:status'];

    public function testNothingIsRegisteredWithoutConfiguration(): void
    {
        $kernel = self::bootKernel();
        $container = self::getContainer();

        foreach ([GeocoderInterface::class, RouterInterface::class, 'geo.geocoder', 'geo.routing.router', 'geo.geocoder.autocompleter'] as $id) {
            $this->assertFalse($container->has($id), $id);
        }
        $this->assertSame([], $this->bundleCommands($kernel));
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function geocoderServices(): iterable
    {
        yield 'geocoder' => [GeocoderInterface::class, Geocoder::class];
        yield 'address lookup' => [AddressLookupInterface::class, AddressLookup::class];
        yield 'search backend' => [SearchBackendInterface::class, SearchBackendInterface::class];
        yield 'house numbers' => [HouseNumberStoreInterface::class, PdoHouseNumberStore::class];
        yield 'name catalog' => [NameCatalogInterface::class, NameCatalogInterface::class];
        yield 'name normalizer' => [NameNormalizerInterface::class, NameNormalizerInterface::class];
        yield 'reranker' => [RerankerInterface::class, RerankerInterface::class];
        yield 'label formatter' => [LabelFormatterInterface::class, LabelFormatterInterface::class];
        yield 'query parser' => [QueryParserInterface::class, QueryParserInterface::class];
        yield 'country recognizer' => [CountryRecognizerInterface::class, CatalogCountryRecognizer::class];
        yield 'country name source' => [CountryNameSourceInterface::class, PdoCountryNameSource::class];
        yield 'index manager' => [IndexManagerInterface::class, MeilisearchIndexManager::class];
        yield 'index naming' => [IndexNamingInterface::class, IndexNamingInterface::class];
        yield 'Meilisearch client' => ['geo.geocoder.meilisearch.client', Client::class];
        yield 'Meilisearch HTTP' => ['geo.geocoder.meilisearch.http', \Meilisearch\Http\Client::class];
        yield 'PSR-18 client of Meilisearch' => ['geo.geocoder.meilisearch.http_client', ClientInterface::class];
        yield 'selection rules' => ['geo.geocoder.selection_rules', SelectionRules::class];
        yield 'search options' => ['geo.geocoder.search_options', SearchOptions::class];
        yield 'importer' => [ImporterInterface::class, Importer::class];
        yield 'status provider' => [GeocoderStatusProviderInterface::class, GeocoderStatusProvider::class];
        yield 'file exporter' => [FileExporterInterface::class, FileExporter::class];
        yield 'converter' => [ConverterInterface::class, ExportAwareConverter::class];
        yield 'clock' => [ClockInterface::class, SystemClock::class];
        yield 'build storage' => [BuildStorageInterface::class, BuildStorageInterface::class];
        yield 'SQL dialect' => [SqlDialectInterface::class, SqlDialectInterface::class];
        yield 'index settings' => ['geo.geocoder.index_settings', IndexSettings::class];
        yield 'artifact installer factory' => [ArtifactInstallerFactoryInterface::class, ArtifactInstallerFactory::class];
        yield 'artifact installer' => [ArtifactInstallerInterface::class, ArtifactInstallerInterface::class];
        yield 'dump downloader' => [DumpDownloaderInterface::class, HttpDumpDownloader::class];
        yield 'region URL resolver' => [RegionUrlResolverInterface::class, GraphHopperRegionUrlResolver::class];
        yield 'PSR-18 client of the downloads' => ['geo.geocoder.import.download_http_client', ClientInterface::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('geocoderServices')]
    public function testGeocoderServiceIsRegistered(string $id, string $class): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder()]]);

        $this->assertInstanceOf($class, self::getContainer()->get($id));
    }

    public function testGeocoderAloneRegistersItsCommandsOnly(): void
    {
        $kernel = self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder()]]);

        $this->assertSame(self::GEOCODER_COMMANDS, $this->bundleCommands($kernel));
        $this->assertFalse(self::getContainer()->has(RouterInterface::class));
    }

    public function testConfigurationReachesTheServices(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder([
            'meilisearch' => ['url' => 'http://127.0.0.1:7700', 'index' => 'places'],
            'database' => ['dsn' => Configs::UNREACHABLE_DSN, 'tables' => ['house_numbers' => 'h', 'translations' => 't', 'metadata' => 'm']],
            'search' => ['default_limit' => 7, 'preferred_country' => 'CZ'],
            'import' => ['languages' => ['sk', 'en'], 'place_types' => ['city' => 99], 'pois' => ['railway' => ['station']], 'poi_rank' => 1, 'street_rank' => 2, 'place_dedup_km' => 0.5, 'dumps_dir' => '/srv/dumps',
                'street_excluded_highways' => ['bus_stop'], 'reference_keys' => ['CZ' => 'ref:ruian:addr'], 'regions' => ['europe/slovakia'], 'download_url' => 'http://mirror.local/photon'],
        ])]]);
        $container = self::getContainer();

        $this->assertEquals(new TableNames('h', 't', 'm'), $container->get('geo.geocoder.tables'));
        $this->assertEquals(new SearchOptions(limit: 7, preferredCountry: 'CZ'), $container->get('geo.geocoder.search_options'));
        $this->assertEquals(new SelectionRules(['city' => 99], ['railway' => ['station']], 1, 2, ['sk', 'en'], 0.5, ['bus_stop'], ['CZ' => 'ref:ruian:addr']), $container->get('geo.geocoder.selection_rules'));
        $this->assertEquals(new GraphHopperRegionUrlResolver('http://mirror.local/photon'), $container->get(RegionUrlResolverInterface::class));
        $this->assertSame(['europe/slovakia'], $container->getParameter('geo.geocoder.import.regions'));
        $naming = $container->get(IndexNamingInterface::class);
        $this->assertInstanceOf(IndexNamingInterface::class, $naming);
        $this->assertSame('places', $naming->liveUid());
        $this->assertSame('/srv/dumps', $container->getParameter('geo.geocoder.import.dumps_dir'));
    }

    public function testDatabaseIsNotConnectedWhenTheGeocoderIsBuilt(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder()]]);

        // the DSN points to a directory that does not exist: connecting would throw
        $this->assertInstanceOf(Geocoder::class, self::getContainer()->get(GeocoderInterface::class));
        $connection = self::getContainer()->get('geo.geocoder.connection');
        $this->assertInstanceOf(\PDO::class, $connection);

        $this->expectException(GeocoderUnavailableException::class);
        $connection->query('SELECT 1');
    }

    public function testDatabaseIsNotConnectedWhenTheImportIsBuilt(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder([
            'meilisearch' => ['url' => 'http://127.0.0.1:7700', 'snapshot_dir' => '/srv/meili/snapshots'],
        ])]]);
        $container = self::getContainer();

        // the DSN points to a directory that does not exist: connecting would throw
        $this->assertInstanceOf(Importer::class, $container->get(ImporterInterface::class));
        $this->assertInstanceOf(ArtifactBuilderInterface::class, $container->get(ArtifactBuilderInterface::class));
        $this->assertInstanceOf(SqlDialectInterface::class, $container->get(SqlDialectInterface::class));
        $this->assertInstanceOf(ArtifactInstallerInterface::class, $container->get(ArtifactInstallerInterface::class));
        $storage = $container->get(BuildStorageInterface::class);
        $this->assertInstanceOf(BuildStorageInterface::class, $storage);

        $this->expectException(GeocoderUnavailableException::class);
        $storage->exists(new TableNames());
    }

    public function testArtifactBuilderNeedsTheSnapshotDirectory(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder()]]);

        $this->assertFalse(self::getContainer()->has(ArtifactBuilderInterface::class));
        $this->assertFalse(self::getContainer()->has('geo.geocoder.artifact_builder'));
    }

    public function testConfiguredMeilisearchVersionReachesTheInstaller(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder([
            'meilisearch' => ['url' => 'http://127.0.0.1:1', 'version' => '1.35.1'],
        ])]]);
        $installer = self::getContainer()->get(ArtifactInstallerInterface::class);
        $this->assertInstanceOf(ArtifactInstallerInterface::class, $installer);

        // created without asking the instance (unreachable): the artifact is checked first
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not an artifact');
        $installer->install('/nonexistent-tito10047-geo/artifact', '/nonexistent-tito10047-geo/data', new \PDO('sqlite::memory:'));
    }

    public function testNameCatalogIsResetBetweenRequests(): void
    {
        $kernel = self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder(['database' => ['connection' => 'app.pdo']])]]);
        $container = self::getContainer();
        $pdo = $container->get('app.pdo');
        $this->assertInstanceOf(\PDO::class, $pdo);
        $pdo->exec('CREATE TABLE geo_name (cc CHAR(2) NOT NULL, kind VARCHAR(8) NOT NULL, name VARCHAR(255) NOT NULL, lang VARCHAR(3) NOT NULL, translation VARCHAR(255) NOT NULL)');
        $pdo->exec("INSERT INTO geo_name VALUES ('AT', 'city', 'Wien', 'sk', 'Viedeň')");
        $catalog = $container->get(NameCatalogInterface::class);
        $this->assertInstanceOf(NameCatalogInterface::class, $catalog);
        $this->assertSame('Viedeň', $catalog->translate('AT', NameKind::City, 'Wien', 'sk'));
        $pdo->exec("UPDATE geo_name SET translation = 'Vieden'");
        $this->assertSame('Viedeň', $catalog->translate('AT', NameKind::City, 'Wien', 'sk'), 'Remembered.');

        $resetter = $kernel->getContainer()->get('services_resetter');
        $this->assertInstanceOf(ResetInterface::class, $resetter);
        $resetter->reset();

        $this->assertSame('Vieden', $catalog->translate('AT', NameKind::City, 'Wien', 'sk'));
    }

    public function testCountryNamesAreReadAgainBetweenRequests(): void
    {
        $kernel = self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder(['database' => ['connection' => 'app.pdo']])]]);
        $container = self::getContainer();
        $pdo = $container->get('app.pdo');
        $this->assertInstanceOf(\PDO::class, $pdo);
        $pdo->exec('CREATE TABLE geo_name (cc CHAR(2) NOT NULL, kind VARCHAR(8) NOT NULL, name VARCHAR(255) NOT NULL, lang VARCHAR(3) NOT NULL, translation VARCHAR(255) NOT NULL)');
        $pdo->exec("INSERT INTO geo_name VALUES ('SK', 'country', 'SK', 'sk', 'Slovensko'), ('SK', 'city', 'Košice', 'hu', 'Kassa')");
        $parser = $container->get(QueryParserInterface::class);
        $this->assertInstanceOf(QueryParserInterface::class, $parser);
        $countries = static fn (string $text): array => $parser->parse($text)?->countries() ?? [];
        $this->assertSame(['SK'], $countries('Košice, Slovensko'));
        $pdo->exec("UPDATE geo_name SET translation = 'Slovakia' WHERE kind = 'country'");
        $this->assertSame(['SK'], $countries('Košice, Slovensko'), 'Remembered.');

        $resetter = $kernel->getContainer()->get('services_resetter');
        $this->assertInstanceOf(ResetInterface::class, $resetter);
        $resetter->reset();

        $this->assertSame([], $countries('Košice, Slovensko'));
        $this->assertSame(['SK'], $countries('Košice, Slovakia'));
    }

    public function testExistingServicesReplaceTheBuiltOnes(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder([
            'meilisearch' => ['client' => 'app.meilisearch'],
            'database' => ['connection' => 'app.pdo'],
        ])]]);
        $container = self::getContainer();

        $this->assertSame($container->get('app.meilisearch'), $container->get('geo.geocoder.meilisearch.client'));
        $this->assertSame($container->get('app.pdo'), $container->get('geo.geocoder.connection'));
        $this->assertInstanceOf(Geocoder::class, $container->get(GeocoderInterface::class));
    }

    public function testIndexManagerNeedsTheUrl(): void
    {
        $kernel = self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder([
            'meilisearch' => ['client' => 'app.meilisearch'],
        ])]]);
        $container = self::getContainer();

        $this->assertFalse($container->has(IndexManagerInterface::class));
        $this->assertFalse($container->has('geo.geocoder.meilisearch.http'));
        $this->assertFalse($container->has(ImporterInterface::class));
        $this->assertFalse($container->has(GeocoderStatusProviderInterface::class));
        $this->assertFalse($container->has(ArtifactBuilderInterface::class));
        // files only: the export and the installation of artifacts
        $this->assertInstanceOf(FileExporter::class, $container->get(FileExporterInterface::class));
        $this->assertInstanceOf(ArtifactInstallerFactory::class, $container->get(ArtifactInstallerFactoryInterface::class));
        $this->assertSame(['geo:artifact:install', 'geo:download', 'geo:import', 'geo:search'], $this->bundleCommands($kernel));
    }

    public function testExistingPsr18ClientsReplaceTheBuiltOnes(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder([
            'meilisearch' => ['url' => 'http://127.0.0.1:7700', 'http_client' => 'app.psr18'],
            'import' => ['download_http_client' => 'app.psr18'],
        ]) + Configs::routing(['http_client' => 'app.psr18'])]]);
        $container = self::getContainer();

        $this->assertSame($container->get('app.psr18'), $container->get('geo.geocoder.meilisearch.http_client'));
        $this->assertSame($container->get('app.psr18'), $container->get('geo.geocoder.import.download_http_client'));
        $this->assertSame($container->get('app.psr18'), $container->get('geo.routing.http_client'));
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function routingServices(): iterable
    {
        yield 'router' => [RouterInterface::class, ValhallaRouter::class];
        yield 'matrix' => [MatrixInterface::class, ValhallaMatrix::class];
        yield 'matrix request encoder' => [MatrixRequestEncoderInterface::class, MatrixRequestEncoderInterface::class];
        yield 'matrix response parser' => [MatrixResponseParserInterface::class, MatrixResponseParserInterface::class];
        yield 'matrix error translator' => ['geo.routing.matrix_error_translator', ErrorTranslatorInterface::class];
        yield 'status provider' => [RoutingStatusProviderInterface::class, ValhallaStatusProvider::class];
        yield 'request encoder' => [RequestEncoderInterface::class, RequestEncoderInterface::class];
        yield 'response parser' => [ResponseParserInterface::class, ResponseParserInterface::class];
        yield 'error translator' => [ErrorTranslatorInterface::class, ErrorTranslatorInterface::class];
        yield 'polyline decoder' => [PolylineDecoderInterface::class, PolylineDecoderInterface::class];
        yield 'country locator' => [CountryLocatorInterface::class, CountryLocatorInterface::class];
        yield 'toll locator' => [TollLocatorInterface::class, TollLocatorInterface::class];
        yield 'PSR-18 client of Valhalla' => ['geo.routing.http_client', ClientInterface::class];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('routingServices')]
    public function testRoutingServiceIsRegistered(string $id, string $class): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::routing()]]);

        $this->assertInstanceOf($class, self::getContainer()->get($id));
    }

    public function testRoutingAloneRegistersItsCommandsOnly(): void
    {
        $kernel = self::bootKernel(['extension_configs' => ['geo' => Configs::routing()]]);

        $this->assertSame(self::ROUTING_COMMANDS, $this->bundleCommands($kernel));
        $this->assertFalse(self::getContainer()->has(GeocoderInterface::class));
        $this->assertFalse(self::getContainer()->has('geo.geocoder.connection'));
    }

    public function testBothSectionsRegisterEveryCommand(): void
    {
        $kernel = self::bootKernel(['extension_configs' => ['geo' => Configs::both()]]);

        $commands = [...self::GEOCODER_COMMANDS, ...self::ROUTING_COMMANDS];
        sort($commands);

        $this->assertSame($commands, $this->bundleCommands($kernel));
    }

    public function testAutocompleterIsRegisteredWhenUxAutocompleteIsInstalled(): void
    {
        if (!TestKernel::hasUxAutocomplete()) {
            $this->markTestSkipped('symfony/ux-autocomplete 3.5 or newer is not installed.');
        }
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder(['ux_autocomplete' => ['alias' => 'address']])]]);

        $registry = self::getContainer()->get('ux.autocomplete.autocompleter_registry');
        $this->assertInstanceOf(AutocompleterRegistry::class, $registry);
        $this->assertInstanceOf(GeocoderAutocompleter::class, $registry->getAutocompleter('address'));
    }

    public function testAutocompleterCanBeDisabled(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder(['ux_autocomplete' => false])]]);

        $this->assertFalse(self::getContainer()->has('geo.geocoder.autocompleter'));
    }

    public function testPhotonApiIsRegisteredWhenEnabled(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder(['photon_api' => ['max_age' => 60]])]]);
        $container = self::getContainer();

        $this->assertInstanceOf(PhotonSearch::class, $container->get(PhotonSearchInterface::class));
        $this->assertInstanceOf(FeatureFactory::class, $container->get(FeatureFactoryInterface::class));
        $this->assertInstanceOf(PhotonApiController::class, $container->get('geo.geocoder.photon_api.controller'));
        $this->assertTrue($container->getParameter(PhotonApiRouteLoader::ENABLED_PARAMETER));
    }

    public function testThePhotonApiKeepsThePartialMatches(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder(['photon_api' => true, 'search' => ['default_limit' => 7, 'complete_matches_only' => true]])]]);
        $container = self::getContainer();

        $this->assertEquals(new SearchOptions(limit: 7, completeMatchesOnly: true), $container->get('geo.geocoder.search_options'));
        $this->assertEquals(new SearchOptions(limit: 7), $container->get('geo.geocoder.photon_api.search_options'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function withoutPhotonApi(): iterable
    {
        yield 'by default' => [Configs::geocoder()];
        yield 'without the geocoder' => [['geocoder' => ['enabled' => false, 'photon_api' => true]]];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('withoutPhotonApi')]
    public function testPhotonApiIsNotRegisteredWhenDisabled(array $config): void
    {
        self::bootKernel(['extension_configs' => ['geo' => $config]]);
        $container = self::getContainer();

        $this->assertFalse($container->has('geo.geocoder.photon_api.controller'));
        $this->assertFalse($container->has(PhotonSearchInterface::class));
        $this->assertFalse($container->getParameter(PhotonApiRouteLoader::ENABLED_PARAMETER));
        $this->assertInstanceOf(PhotonApiRouteLoader::class, $container->get('geo.photon_api.route_loader'));
    }

    public function testRoutingWithAnEmptyUrlIsCreatedButNotConfigured(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::routing(['url' => Configs::EMPTY_ENV])]]);
        $container = self::getContainer();

        foreach ([RouterInterface::class, MatrixInterface::class, RoutingStatusProviderInterface::class] as $id) {
            $this->assertInstanceOf(UnconfiguredValhalla::class, $container->get($id), $id);
        }
        // the services injecting them
        foreach (['geo.routing.command.route', 'geo.routing.command.matrix', 'geo.routing.command.status'] as $id) {
            $this->assertInstanceOf(Command::class, $container->get($id), $id);
        }
        $router = $container->get(RouterInterface::class);
        $this->assertInstanceOf(RouterInterface::class, $router);

        $this->expectException(RoutingUnavailableException::class);
        $this->expectExceptionMessage('Valhalla is not configured: geo.routing.url is empty.');
        $router->route(new RouteRequest([new RoutingCoordinates(48.1486, 17.1077), new RoutingCoordinates(48.7164, 21.2611)]));
    }

    public function testAnEmptyEnvironmentVariableSwitchesTheRoutingOff(): void
    {
        $_SERVER['GEO_TEST_VALHALLA_URL'] = '';
        try {
            self::bootKernel(['extension_configs' => ['geo' => Configs::routing(['url' => '%env(GEO_TEST_VALHALLA_URL)%'])]]);

            $this->assertInstanceOf(UnconfiguredValhalla::class, self::getContainer()->get(RouterInterface::class));
        } finally {
            unset($_SERVER['GEO_TEST_VALHALLA_URL']);
        }
    }

    public function testRoutingWithAnInvalidUrlFailsWhenCreated(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::routing(['url' => 'valhalla:8002'])]]);

        $this->expectException(RoutingInvalidArgumentException::class);
        $this->expectExceptionMessage('The Valhalla URL must be an absolute HTTP or HTTPS URL, "valhalla:8002" given.');
        self::getContainer()->get(RouterInterface::class);
    }

    public function testGeocoderWithAnEmptyUrlIsCreatedButNotConfigured(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder([
            'meilisearch' => ['url' => Configs::EMPTY_ENV, 'api_key' => Configs::EMPTY_ENV],
            'photon_api' => true,
        ])]]);
        $container = self::getContainer();

        foreach ([GeocoderInterface::class, AddressLookupInterface::class, SearchBackendInterface::class, DocumentSourceInterface::class, IndexManagerInterface::class] as $id) {
            $this->assertInstanceOf(UnconfiguredMeilisearch::class, $container->get($id), $id);
        }
        // the services injecting them
        $this->assertInstanceOf(Importer::class, $container->get(ImporterInterface::class));
        $this->assertInstanceOf(PhotonApiController::class, $container->get('geo.geocoder.photon_api.controller'));
        foreach (['geo.geocoder.command.search', 'geo.geocoder.command.status', 'geo.geocoder.command.import', 'geo.geocoder.command.publish', 'geo.geocoder.command.cleanup'] as $id) {
            $this->assertInstanceOf(Command::class, $container->get($id), $id);
        }
        if (TestKernel::hasUxAutocomplete()) {
            $this->assertInstanceOf(GeocoderAutocompleter::class, $container->get('geo.geocoder.autocompleter'));
        }
    }

    public function testIndexUidFromAnEnvironmentVariable(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder(['meilisearch' => ['url' => Configs::EMPTY_ENV, 'index' => Configs::INDEX_ENV]])]]);
        $container = self::getContainer();

        $naming = $container->get(IndexNamingInterface::class);
        $this->assertInstanceOf(IndexNamingInterface::class, $naming);
        $this->assertSame('places', $naming->liveUid());
        $provider = $container->get(GeocoderStatusProviderInterface::class);
        $this->assertInstanceOf(GeocoderStatusProviderInterface::class, $provider);
        $this->assertSame('places', $provider->status()->indexUid);
    }

    public function testIndexUidFromAnEnvironmentVariableWithoutDefault(): void
    {
        $_SERVER['GEO_TEST_RUNTIME_INDEX'] = 'tankovac_geo';
        try {
            self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder(['meilisearch' => ['url' => 'http://127.0.0.1:7700', 'index' => Configs::RUNTIME_INDEX_ENV]])]]);

            $naming = self::getContainer()->get(IndexNamingInterface::class);
            $this->assertInstanceOf(IndexNamingInterface::class, $naming);
            $this->assertSame('tankovac_geo', $naming->liveUid());
        } finally {
            unset($_SERVER['GEO_TEST_RUNTIME_INDEX']);
        }
    }

    public function testInvalidIndexUidFromAnEnvironmentVariableFailsWhenUsed(): void
    {
        $_SERVER['GEO_TEST_RUNTIME_INDEX'] = 'geo index';
        try {
            self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder(['meilisearch' => ['url' => 'http://127.0.0.1:7700', 'index' => Configs::RUNTIME_INDEX_ENV]])]]);

            self::getContainer()->get(IndexNamingInterface::class);
            $this->fail('The invalid index uid was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('"geo index" is not a valid index uid', $e->getMessage());
        } finally {
            unset($_SERVER['GEO_TEST_RUNTIME_INDEX']);
        }
    }

    public function testGeocoderWithAnEmptyUrlReportsItIsNotConfigured(): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder(['meilisearch' => ['url' => Configs::EMPTY_ENV, 'index' => 'places']])]]);
        $provider = self::getContainer()->get(GeocoderStatusProviderInterface::class);
        $this->assertInstanceOf(GeocoderStatusProviderInterface::class, $provider);

        $status = $provider->status();

        $this->assertSame(GeocoderState::NotConfigured, $status->state);
        $this->assertSame('places', $status->indexUid);
        $this->assertSame(UnconfiguredMeilisearch::MESSAGE, $status->error);
    }

    /**
     * @return iterable<string, array{class-string, \Closure(object): mixed}>
     */
    public static function geocoderCalls(): iterable
    {
        yield 'suggest' => [GeocoderInterface::class, static fn (object $geocoder): mixed => $geocoder instanceof GeocoderInterface ? $geocoder->suggest('Hlavná 12 Košice') : null];
        yield 'geocode' => [GeocoderInterface::class, static fn (object $geocoder): mixed => $geocoder instanceof GeocoderInterface ? $geocoder->geocode('Hlavná 12 Košice') : null];
        yield 'findByReference' => [AddressLookupInterface::class, static fn (object $lookup): mixed => $lookup instanceof AddressLookupInterface ? $lookup->findByReference('1234567') : null];
    }

    /**
     * @param class-string            $id
     * @param \Closure(object): mixed $call
     */
    #[DataProvider('geocoderCalls')]
    public function testGeocoderWithAnEmptyUrlIsUnavailable(string $id, \Closure $call): void
    {
        self::bootKernel(['extension_configs' => ['geo' => Configs::geocoder(['meilisearch' => ['url' => Configs::EMPTY_ENV]])]]);
        $service = self::getContainer()->get($id);
        $this->assertIsObject($service);

        $this->expectException(GeocoderUnavailableException::class);
        $this->expectExceptionMessage('Meilisearch is not configured: geo.geocoder.meilisearch.url is empty.');
        $call($service);
    }

    /**
     * Supports the "extension_configs" option, see TestKernel::__construct().
     *
     * @param array<mixed> $options
     */
    protected static function createKernel(array $options = []): TestKernel
    {
        return new TestKernel(extensionConfigs: $options['extension_configs'] ?? []);
    }

    /**
     * @return list<string> the names of the commands of the bundle, sorted
     */
    private function bundleCommands(KernelInterface $kernel): array
    {
        $names = array_values(array_filter(array_keys((new Application($kernel))->all()), static fn (string $name): bool => str_starts_with($name, 'geo:')));
        sort($names);

        return $names;
    }
}
