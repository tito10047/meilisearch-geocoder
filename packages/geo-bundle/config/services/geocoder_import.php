<?php

use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\GeoBundle\Artifact\ArtifactInstallerFactory;
use Tito10047\GeoBundle\Artifact\ArtifactInstallerFactoryInterface;
use Tito10047\GeoBundle\Command\GeocoderArtifactCommand;
use Tito10047\GeoBundle\Command\GeocoderCleanupCommand;
use Tito10047\GeoBundle\Command\GeocoderDownloadCommand;
use Tito10047\GeoBundle\Command\GeocoderImportCommand;
use Tito10047\GeoBundle\Command\GeocoderInstallArtifactCommand;
use Tito10047\GeoBundle\Command\GeocoderPublishCommand;
use Tito10047\GeoBundle\Http\HttpClientFactory;
use Tito10047\Geocoder\Artifact\ArtifactBuilder;
use Tito10047\Geocoder\Artifact\ArtifactBuilderInterface;
use Tito10047\Geocoder\Artifact\ArtifactInstaller;
use Tito10047\Geocoder\Artifact\ArtifactInstallerInterface;
use Tito10047\Geocoder\Import\ClockInterface;
use Tito10047\Geocoder\Import\ConverterInterface;
use Tito10047\Geocoder\Import\Download\DumpDownloaderInterface;
use Tito10047\Geocoder\Import\Download\GraphHopperRegionUrlResolver;
use Tito10047\Geocoder\Import\Download\HttpDumpDownloader;
use Tito10047\Geocoder\Import\Download\RegionUrlResolverInterface;
use Tito10047\Geocoder\Import\File\ExportAwareConverter;
use Tito10047\Geocoder\Import\File\FileExportConverter;
use Tito10047\Geocoder\Import\File\FileExporter;
use Tito10047\Geocoder\Import\File\FileExporterInterface;
use Tito10047\Geocoder\Import\Importer;
use Tito10047\Geocoder\Import\ImporterInterface;
use Tito10047\Geocoder\Import\Photon\PhotonConverter;
use Tito10047\Geocoder\Import\SystemClock;
use Tito10047\Geocoder\Index\IndexSettings;
use Tito10047\Geocoder\Storage\BuildStorageInterface;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialectInterface;
use Tito10047\Geocoder\Storage\Pdo\Dialect\SqlDialects;
use Tito10047\Geocoder\Storage\Pdo\PdoBuildStorage;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The services of the import of the geocoder (tito10047/geocoder), loaded with those of the
 * geocoder. Everything touching the database is lazy: the connection is opened by the first
 * query, not when a command or a service using it is built. Without the URL of Meilisearch,
 * Tito10047GeoBundle::loadGeocoder() removes what needs the index manager (the importer, the
 * artifact builder and their commands); the exports and the installation of artifacts remain.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services
        // what turns a dump into documents, house numbers and names: Photon dumps, and the exports of FileExporter
        ->set('geo.geocoder.import.photon_converter', PhotonConverter::class)
            ->args([service('geo.geocoder.selection_rules')])

        ->set('geo.geocoder.import.export_converter', FileExportConverter::class)

        ->set('geo.geocoder.import.converter', ExportAwareConverter::class)
            ->args([service('geo.geocoder.import.photon_converter'), service('geo.geocoder.import.export_converter')])
        ->alias(ConverterInterface::class, 'geo.geocoder.import.converter')

        // names the builds; symfony/clock is not a dependency of the bundle
        ->set('geo.geocoder.import.clock', SystemClock::class)
        ->alias(ClockInterface::class, 'geo.geocoder.import.clock')

        ->set('geo.geocoder.index_settings', IndexSettings::class)

        // the tables of the builds: the dialect is chosen by the driver of the connection, so both wait for it
        ->set('geo.geocoder.sql_dialect', SqlDialectInterface::class)
            ->factory(SqlDialects::forConnection(...))
            ->args([service('geo.geocoder.connection')])
            ->lazy(SqlDialectInterface::class)
        ->alias(SqlDialectInterface::class, 'geo.geocoder.sql_dialect')

        ->set('geo.geocoder.build_storage', PdoBuildStorage::class)
            ->args([service('geo.geocoder.connection'), service('geo.geocoder.sql_dialect')])
            ->lazy(BuildStorageInterface::class)
        ->alias(BuildStorageInterface::class, 'geo.geocoder.build_storage')

        ->set('geo.geocoder.importer', Importer::class)
            ->args([
                service('geo.geocoder.import.converter'),
                service('geo.geocoder.index_manager'),
                service('geo.geocoder.build_storage'),
                service('geo.geocoder.index_naming'),
                service('geo.geocoder.index_settings'),
                service('geo.geocoder.tables'),
                service('geo.geocoder.import.clock'),
                service('logger'),
            ])
            ->tag('monolog.logger', ['channel' => 'geo'])
        ->alias(ImporterInterface::class, 'geo.geocoder.importer')

        ->set('geo.geocoder.file_exporter', FileExporter::class)
            ->args([
                service('geo.geocoder.import.converter'),
                service('geo.geocoder.index_naming'),
                service('geo.geocoder.index_settings'),
                service('geo.geocoder.import.clock'),
            ])
        ->alias(FileExporterInterface::class, 'geo.geocoder.file_exporter')

        // artifacts: the builder needs the snapshot directory, the installer the version of the target instance
        ->set('geo.geocoder.artifact_builder', ArtifactBuilder::class)
            ->args([
                service('geo.geocoder.index_manager'),
                service('geo.geocoder.connection'),
                abstract_arg('snapshot directory'),
                service('geo.geocoder.index_naming'),
                service('geo.geocoder.tables'),
                service('geo.geocoder.import.clock'),
            ])
            ->lazy(ArtifactBuilderInterface::class)
        ->alias(ArtifactBuilderInterface::class, 'geo.geocoder.artifact_builder')

        ->set('geo.geocoder.artifact_installer_factory', ArtifactInstallerFactory::class)
            ->args([
                service('geo.geocoder.index_naming'),
                service('geo.geocoder.tables'),
                abstract_arg('Meilisearch version'),
                service('geo.geocoder.index_manager')->nullOnInvalid(),
            ])
        ->alias(ArtifactInstallerFactoryInterface::class, 'geo.geocoder.artifact_installer_factory')

        // the version may have to be read from the instance: not before the first installation
        ->set('geo.geocoder.artifact_installer', ArtifactInstaller::class)
            ->factory([service('geo.geocoder.artifact_installer_factory'), 'create'])
            ->lazy(ArtifactInstallerInterface::class)
        ->alias(ArtifactInstallerInterface::class, 'geo.geocoder.artifact_installer')

        // the downloads of the dumps
        ->set('geo.geocoder.import.download_http_client', ClientInterface::class)
            ->factory(HttpClientFactory::class.'::download')

        ->set('geo.geocoder.import.region_url_resolver', GraphHopperRegionUrlResolver::class)
            ->args([abstract_arg('base URL of the dumps')])
        ->alias(RegionUrlResolverInterface::class, 'geo.geocoder.import.region_url_resolver')

        ->set('geo.geocoder.import.dump_downloader', HttpDumpDownloader::class)
            ->args([
                service('geo.geocoder.import.download_http_client'),
                service('geo.psr17_factory'),
                service('geo.geocoder.import.region_url_resolver'),
            ])
        ->alias(DumpDownloaderInterface::class, 'geo.geocoder.import.dump_downloader')

        // commands
        ->set('geo.geocoder.command.download', GeocoderDownloadCommand::class)
            ->args([
                service('geo.geocoder.import.dump_downloader'),
                param('geo.geocoder.import.regions'),
                param('geo.geocoder.import.dumps_dir'),
            ])
            ->tag('console.command', ['command' => 'geo:download'])

        ->set('geo.geocoder.command.import', GeocoderImportCommand::class)
            ->args([
                service('geo.geocoder.importer')->nullOnInvalid(),
                service('geo.geocoder.file_exporter'),
                param('geo.geocoder.import.dumps_dir'),
            ])
            ->tag('console.command', ['command' => 'geo:import'])

        ->set('geo.geocoder.command.publish', GeocoderPublishCommand::class)
            ->args([service('geo.geocoder.importer')])
            ->tag('console.command', ['command' => 'geo:publish'])

        ->set('geo.geocoder.command.cleanup', GeocoderCleanupCommand::class)
            ->args([service('geo.geocoder.importer')])
            ->tag('console.command', ['command' => 'geo:cleanup'])

        ->set('geo.geocoder.command.artifact', GeocoderArtifactCommand::class)
            ->args([service('geo.geocoder.importer'), service('geo.geocoder.artifact_builder')->nullOnInvalid()])
            ->tag('console.command', ['command' => 'geo:artifact'])

        ->set('geo.geocoder.command.install_artifact', GeocoderInstallArtifactCommand::class)
            ->args([service('geo.geocoder.artifact_installer_factory'), service('geo.geocoder.connection')])
            ->tag('console.command', ['command' => 'geo:artifact:install'])
    ;
};
