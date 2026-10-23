<?php

namespace Tito10047\GeoBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\UX\Autocomplete\AutocompleterInterface;
use Tito10047\GeoBundle\Exception\LogicException;

/**
 * Entry point of the bundle: loads its configuration and its services into the container of the application.
 *
 * @see https://symfony.com/doc/current/bundles/best_practices.html
 */
final class Tito10047GeoBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/definition.php');
    }

    /**
     * @param array<mixed> $config the processed configuration of the bundle
     *
     * @throws LogicException when the configuration asks for symfony/ux-autocomplete and it is missing
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        /** @var array{enabled: bool, meilisearch: array{url: ?string, api_key: ?string, client: ?string, http_client: ?string, timeout: float, compression: bool, index: string}, database: array{dsn: ?string, user: ?string, password: ?string, connection: ?string, tables: array{house_numbers: string, translations: string, metadata: string}}, import: array{dumps_dir: ?string, languages: list<string>, place_types: array<string, int>, pois: array<string, list<string>>, poi_rank: int, street_rank: int, place_dedup_km: float}, search: array{default_limit: int, preferred_country: ?string, max_rank_gap: int, rerank_window: int}, ux_autocomplete: array{enabled: bool|'auto', alias: string}} $geocoder */
        $geocoder = $config['geocoder'];
        if ($geocoder['enabled']) {
            $this->loadGeocoder($geocoder, $container, $builder);
        }

        /** @var array{enabled: bool, url: string, timeout: float, radius: int, http_client: ?string} $routing */
        $routing = $config['routing'];
        if ($routing['enabled']) {
            $this->loadRouting($routing, $container, $builder);
        }
    }

    /**
     * @param array{enabled: bool, meilisearch: array{url: ?string, api_key: ?string, client: ?string, http_client: ?string, timeout: float, compression: bool, index: string}, database: array{dsn: ?string, user: ?string, password: ?string, connection: ?string, tables: array{house_numbers: string, translations: string, metadata: string}}, import: array{dumps_dir: ?string, languages: list<string>, place_types: array<string, int>, pois: array<string, list<string>>, poi_rank: int, street_rank: int, place_dedup_km: float}, search: array{default_limit: int, preferred_country: ?string, max_rank_gap: int, rerank_window: int}, ux_autocomplete: array{enabled: bool|'auto', alias: string}} $config
     *
     * @throws LogicException when the configuration asks for symfony/ux-autocomplete and it is missing
     */
    private function loadGeocoder(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services/geocoder.php');
        $services = $container->services();
        $meilisearch = $config['meilisearch'];
        $database = $config['database'];
        $tables = $database['tables'];
        $search = $config['search'];
        $import = $config['import'];

        // Meilisearch: built from the URL, or an existing client for searching
        $services->get('tito10047_geo.geocoder.meilisearch.http_client')->arg(0, $meilisearch['timeout'])->arg(1, $meilisearch['compression']);
        foreach (['tito10047_geo.geocoder.meilisearch.client', 'tito10047_geo.geocoder.meilisearch.http'] as $id) {
            $services->get($id)->arg(0, $meilisearch['url'])->arg(1, $meilisearch['api_key']);
        }
        if (null !== $meilisearch['http_client']) {
            $builder->removeDefinition('tito10047_geo.geocoder.meilisearch.http_client');
            $services->alias('tito10047_geo.geocoder.meilisearch.http_client', $meilisearch['http_client']);
        }
        if (null !== $meilisearch['client']) {
            $builder->removeDefinition('tito10047_geo.geocoder.meilisearch.client');
            $services->alias('tito10047_geo.geocoder.meilisearch.client', $meilisearch['client']);
        }
        if (null === $meilisearch['url']) {
            // the HTTP layer of an existing client is not reachable: no index manager without a URL
            foreach (['tito10047_geo.geocoder.meilisearch.http_client', 'tito10047_geo.geocoder.meilisearch.http', 'tito10047_geo.geocoder.index_manager', 'tito10047_geo.geocoder.command.status'] as $id) {
                $builder->removeDefinition($id);
            }
            $builder->removeAlias(\Tito10047\Geocoder\Index\IndexManagerInterface::class);
        }

        // the database: a lazy connection from the DSN, or an existing one
        if (null !== $database['connection']) {
            $builder->removeDefinition('tito10047_geo.geocoder.connection');
            $services->alias('tito10047_geo.geocoder.connection', $database['connection']);
        } else {
            $services->get('tito10047_geo.geocoder.connection')->arg(0, $database['dsn'])->arg(1, $database['user'])->arg(2, $database['password']);
        }
        $services->get('tito10047_geo.geocoder.tables')->args([$tables['house_numbers'], $tables['translations'], $tables['metadata']]);

        // search
        $services->get('tito10047_geo.geocoder.search_backend')->arg(1, $meilisearch['index']);
        $services->get('tito10047_geo.geocoder.reranker')->arg(0, $search['max_rank_gap']);
        $services->get('tito10047_geo.geocoder')->arg(6, $search['rerank_window']);
        $services->get('tito10047_geo.geocoder.search_options')->arg(1, $search['default_limit'])->arg(2, $search['preferred_country']);

        // index and import
        $services->get('tito10047_geo.geocoder.index_naming')->arg(0, $meilisearch['index']);
        if ($builder->hasDefinition('tito10047_geo.geocoder.command.status')) {
            $services->get('tito10047_geo.geocoder.command.status')->arg(1, $meilisearch['index']);
        }
        $services->get('tito10047_geo.geocoder.selection_rules')->args([
            $import['place_types'],
            $import['pois'],
            $import['poi_rank'],
            $import['street_rank'],
            $import['languages'],
            $import['place_dedup_km'],
        ]);
        $container->parameters()->set('tito10047_geo.geocoder.import.dumps_dir', $import['dumps_dir']);

        $this->loadUxAutocomplete($config['ux_autocomplete'], $container);
    }

    /**
     * @param array{enabled: bool|'auto', alias: string} $config
     *
     * @throws LogicException when the configuration asks for symfony/ux-autocomplete and it is missing
     */
    private function loadUxAutocomplete(array $config, ContainerConfigurator $container): void
    {
        // AutocompleterInterface, for any data source, came with symfony/ux-autocomplete 3.5
        $installed = interface_exists(AutocompleterInterface::class);
        if (true === $config['enabled'] && !$installed) {
            throw new LogicException('"tito10047_geo.geocoder.ux_autocomplete.enabled" is true but symfony/ux-autocomplete 3.5 or newer is not installed: run "composer require symfony/ux-autocomplete" or set it to "auto".');
        }
        if (false === $config['enabled'] || !$installed) {
            return;
        }

        $container->import('../config/services/ux_autocomplete.php');
        $container->services()->get('tito10047_geo.geocoder.autocompleter')->tag('ux.autocompleter', ['alias' => $config['alias']]);
    }

    /**
     * @param array{enabled: bool, url: string, timeout: float, radius: int, http_client: ?string} $config
     */
    private function loadRouting(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services/routing.php');
        $services = $container->services();

        if (null !== $config['http_client']) {
            $builder->removeDefinition('tito10047_geo.routing.http_client');
            $services->alias('tito10047_geo.routing.http_client', $config['http_client']);
        } else {
            $services->get('tito10047_geo.routing.http_client')->arg(0, $config['timeout']);
        }
        $services->get('tito10047_geo.routing.router')->arg(0, $config['url']);
        $services->get('tito10047_geo.routing.status_provider')->arg(0, $config['url']);
        $services->get('tito10047_geo.routing.command.route')->arg(1, $config['radius']);
    }
}
