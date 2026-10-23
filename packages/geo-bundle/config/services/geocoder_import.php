<?php

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\GeoBundle\Artifact\ArtifactInstallerFactory;
use Tito10047\GeoBundle\Artifact\ArtifactInstallerFactoryInterface;
use Tito10047\GeoBundle\Command\GeocoderArtifactCommand;
use Tito10047\GeoBundle\Command\GeocoderCleanupCommand;
use Tito10047\GeoBundle\Command\GeocoderImportCommand;
use Tito10047\GeoBundle\Command\GeocoderInstallArtifactCommand;
use Tito10047\GeoBundle\Command\GeocoderPublishCommand;
use Tito10047\Geocoder\Artifact\ArtifactBuilder;
use Tito10047\Geocoder\Artifact\ArtifactBuilderInterface;
use Tito10047\Geocoder\Artifact\ArtifactInstaller;
use Tito10047\Geocoder\Artifact\ArtifactInstallerInterface;
use Tito10047\Geocoder\Import\ClockInterface;
use Tito10047\Geocoder\Import\ConverterInterface;
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
        ->set('tito10047_geo.geocoder.import.photon_converter', PhotonConverter::class)
            ->args([service('tito10047_geo.geocoder.selection_rules')])

        ->set('tito10047_geo.geocoder.import.export_converter', FileExportConverter::class)

        ->set('tito10047_geo.geocoder.import.converter', ExportAwareConverter::class)
            ->args([service('tito10047_geo.geocoder.import.photon_converter'), service('tito10047_geo.geocoder.import.export_converter')])
        ->alias(ConverterInterface::class, 'tito10047_geo.geocoder.import.converter')

        // names the builds; symfony/clock is not a dependency of the bundle
        ->set('tito10047_geo.geocoder.import.clock', SystemClock::class)
        ->alias(ClockInterface::class, 'tito10047_geo.geocoder.import.clock')

        ->set('tito10047_geo.geocoder.index_settings', IndexSettings::class)

        // the tables of the builds: the dialect is chosen by the driver of the connection, so both wait for it
        ->set('tito10047_geo.geocoder.sql_dialect', SqlDialectInterface::class)
            ->factory(SqlDialects::forConnection(...))
            ->args([service('tito10047_geo.geocoder.connection')])
            ->lazy(SqlDialectInterface::class)
        ->alias(SqlDialectInterface::class, 'tito10047_geo.geocoder.sql_dialect')

        ->set('tito10047_geo.geocoder.build_storage', PdoBuildStorage::class)
            ->args([service('tito10047_geo.geocoder.connection'), service('tito10047_geo.geocoder.sql_dialect')])
            ->lazy(BuildStorageInterface::class)
        ->alias(BuildStorageInterface::class, 'tito10047_geo.geocoder.build_storage')

        ->set('tito10047_geo.geocoder.importer', Importer::class)
            ->args([
                service('tito10047_geo.geocoder.import.converter'),
                service('tito10047_geo.geocoder.index_manager'),
                service('tito10047_geo.geocoder.build_storage'),
                service('tito10047_geo.geocoder.index_naming'),
                service('tito10047_geo.geocoder.index_settings'),
                service('tito10047_geo.geocoder.tables'),
                service('tito10047_geo.geocoder.import.clock'),
                service('logger'),
            ])
            ->tag('monolog.logger', ['channel' => 'tito10047_geo'])
        ->alias(ImporterInterface::class, 'tito10047_geo.geocoder.importer')

        ->set('tito10047_geo.geocoder.file_exporter', FileExporter::class)
            ->args([
                service('tito10047_geo.geocoder.import.converter'),
                service('tito10047_geo.geocoder.index_naming'),
                service('tito10047_geo.geocoder.index_settings'),
                service('tito10047_geo.geocoder.import.clock'),
            ])
        ->alias(FileExporterInterface::class, 'tito10047_geo.geocoder.file_exporter')

        // artifacts: the builder needs the snapshot directory, the installer the version of the target instance
        ->set('tito10047_geo.geocoder.artifact_builder', ArtifactBuilder::class)
            ->args([
                service('tito10047_geo.geocoder.index_manager'),
                service('tito10047_geo.geocoder.connection'),
                abstract_arg('snapshot directory'),
                service('tito10047_geo.geocoder.index_naming'),
                service('tito10047_geo.geocoder.tables'),
                service('tito10047_geo.geocoder.import.clock'),
            ])
            ->lazy(ArtifactBuilderInterface::class)
        ->alias(ArtifactBuilderInterface::class, 'tito10047_geo.geocoder.artifact_builder')

        ->set('tito10047_geo.geocoder.artifact_installer_factory', ArtifactInstallerFactory::class)
            ->args([
                service('tito10047_geo.geocoder.index_naming'),
                service('tito10047_geo.geocoder.tables'),
                abstract_arg('Meilisearch version'),
                service('tito10047_geo.geocoder.index_manager')->nullOnInvalid(),
            ])
        ->alias(ArtifactInstallerFactoryInterface::class, 'tito10047_geo.geocoder.artifact_installer_factory')

        // the version may have to be read from the instance: not before the first installation
        ->set('tito10047_geo.geocoder.artifact_installer', ArtifactInstaller::class)
            ->factory([service('tito10047_geo.geocoder.artifact_installer_factory'), 'create'])
            ->lazy(ArtifactInstallerInterface::class)
        ->alias(ArtifactInstallerInterface::class, 'tito10047_geo.geocoder.artifact_installer')

        // commands
        ->set('tito10047_geo.geocoder.command.import', GeocoderImportCommand::class)
            ->args([
                service('tito10047_geo.geocoder.importer')->nullOnInvalid(),
                service('tito10047_geo.geocoder.file_exporter'),
                param('tito10047_geo.geocoder.import.dumps_dir'),
            ])
            ->tag('console.command', ['command' => 'tito10047-geo:geocoder:import'])

        ->set('tito10047_geo.geocoder.command.publish', GeocoderPublishCommand::class)
            ->args([service('tito10047_geo.geocoder.importer')])
            ->tag('console.command', ['command' => 'tito10047-geo:geocoder:publish'])

        ->set('tito10047_geo.geocoder.command.cleanup', GeocoderCleanupCommand::class)
            ->args([service('tito10047_geo.geocoder.importer')])
            ->tag('console.command', ['command' => 'tito10047-geo:geocoder:cleanup'])

        ->set('tito10047_geo.geocoder.command.artifact', GeocoderArtifactCommand::class)
            ->args([service('tito10047_geo.geocoder.importer'), service('tito10047_geo.geocoder.artifact_builder')->nullOnInvalid()])
            ->tag('console.command', ['command' => 'tito10047-geo:geocoder:artifact'])

        ->set('tito10047_geo.geocoder.command.install_artifact', GeocoderInstallArtifactCommand::class)
            ->args([service('tito10047_geo.geocoder.artifact_installer_factory'), service('tito10047_geo.geocoder.connection')])
            ->tag('console.command', ['command' => 'tito10047-geo:geocoder:install-artifact'])
    ;
};
