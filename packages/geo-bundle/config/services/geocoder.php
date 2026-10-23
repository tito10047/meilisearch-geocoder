<?php

use Meilisearch\Client;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\GeoBundle\Command\GeocoderSearchCommand;
use Tito10047\GeoBundle\Command\GeocoderStatusCommand;
use Tito10047\GeoBundle\Http\HttpClientFactory;
use Tito10047\Geocoder\HouseNumber\HouseNumberStoreInterface;
use Tito10047\Geocoder\HouseNumber\PdoHouseNumberStore;
use Tito10047\Geocoder\Import\SelectionRules;
use Tito10047\Geocoder\Index\IndexingOptions;
use Tito10047\Geocoder\Index\IndexManagerInterface;
use Tito10047\Geocoder\Index\IndexNamingInterface;
use Tito10047\Geocoder\Index\MeilisearchIndexManager;
use Tito10047\Geocoder\Index\TimestampIndexNaming;
use Tito10047\Geocoder\Name\NameCatalogInterface;
use Tito10047\Geocoder\Name\PdoNameCatalog;
use Tito10047\Geocoder\Search\Backend\MeilisearchSearchBackend;
use Tito10047\Geocoder\Search\Backend\SearchBackendInterface;
use Tito10047\Geocoder\Search\Geocoder;
use Tito10047\Geocoder\Search\GeocoderInterface;
use Tito10047\Geocoder\Search\LabelFormatter;
use Tito10047\Geocoder\Search\LabelFormatterInterface;
use Tito10047\Geocoder\Search\Query\QueryParser;
use Tito10047\Geocoder\Search\Query\QueryParserInterface;
use Tito10047\Geocoder\Search\Reranker;
use Tito10047\Geocoder\Search\RerankerInterface;
use Tito10047\Geocoder\Search\SearchOptions;
use Tito10047\Geocoder\Search\Text\NameNormalizer;
use Tito10047\Geocoder\Search\Text\NameNormalizerInterface;
use Tito10047\Geocoder\Storage\TableNames;

use function Symfony\Component\DependencyInjection\Loader\Configurator\abstract_arg;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

/*
 * The services of the geocoder (tito10047/geocoder), loaded when "tito10047_geo.geocoder" is
 * enabled. Every collaborator is a service of its own, aliased to its interface, so that an
 * application can decorate or replace any of them. The abstract arguments are set from the
 * configuration by Tito10047GeoBundle::loadExtension().
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Meilisearch: the search client and the HTTP layer of the index manager share the URL, the key and the PSR-18 client
    $services
        ->set('tito10047_geo.geocoder.meilisearch.http_client', ClientInterface::class)
            ->factory(HttpClientFactory::class.'::meilisearch')
            ->args([abstract_arg('timeout'), abstract_arg('whether to accept gzip')])

        ->set('tito10047_geo.geocoder.meilisearch.client', Client::class)
            ->args([
                abstract_arg('url'),
                abstract_arg('API key'),
                service('tito10047_geo.geocoder.meilisearch.http_client'),
                service('tito10047_geo.psr17_factory'),
                [],
                service('tito10047_geo.psr17_factory'),
            ])

        ->set('tito10047_geo.geocoder.meilisearch.http', Meilisearch\Http\Client::class)
            ->args([
                abstract_arg('url'),
                abstract_arg('API key'),
                service('tito10047_geo.geocoder.meilisearch.http_client'),
                service('tito10047_geo.psr17_factory'),
                [],
                service('tito10047_geo.psr17_factory'),
            ])

        // the database: connected on the first query, not when the container builds the geocoder
        ->set('tito10047_geo.geocoder.connection', PDO::class)
            ->lazy()
            ->args([abstract_arg('DSN'), abstract_arg('user'), abstract_arg('password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]])

        ->set('tito10047_geo.geocoder.tables', TableNames::class)
            ->args([abstract_arg('house numbers'), abstract_arg('translations'), abstract_arg('metadata')])

        // search
        ->set('tito10047_geo.geocoder.search_backend', MeilisearchSearchBackend::class)
            ->args([service('tito10047_geo.geocoder.meilisearch.client'), abstract_arg('index uid')])
        ->alias(SearchBackendInterface::class, 'tito10047_geo.geocoder.search_backend')

        ->set('tito10047_geo.geocoder.house_number_store', PdoHouseNumberStore::class)
            ->args([service('tito10047_geo.geocoder.connection'), service('tito10047_geo.geocoder.tables')])
        ->alias(HouseNumberStoreInterface::class, 'tito10047_geo.geocoder.house_number_store')

        ->set('tito10047_geo.geocoder.name_catalog', PdoNameCatalog::class)
            ->args([service('tito10047_geo.geocoder.connection'), service('tito10047_geo.geocoder.tables')])
        ->alias(NameCatalogInterface::class, 'tito10047_geo.geocoder.name_catalog')

        ->set('tito10047_geo.geocoder.name_normalizer', NameNormalizer::class)
        ->alias(NameNormalizerInterface::class, 'tito10047_geo.geocoder.name_normalizer')

        ->set('tito10047_geo.geocoder.reranker', Reranker::class)
            ->args([abstract_arg('maximum rank gap'), service('tito10047_geo.geocoder.name_normalizer')])
        ->alias(RerankerInterface::class, 'tito10047_geo.geocoder.reranker')

        ->set('tito10047_geo.geocoder.label_formatter', LabelFormatter::class)
            ->args([service('tito10047_geo.geocoder.name_catalog'), service('tito10047_geo.geocoder.name_normalizer')])
        ->alias(LabelFormatterInterface::class, 'tito10047_geo.geocoder.label_formatter')

        ->set('tito10047_geo.geocoder.query_parser', QueryParser::class)
        ->alias(QueryParserInterface::class, 'tito10047_geo.geocoder.query_parser')

        ->set('tito10047_geo.geocoder', Geocoder::class)
            ->args([
                service('tito10047_geo.geocoder.search_backend'),
                service('tito10047_geo.geocoder.house_number_store'),
                service('tito10047_geo.geocoder.name_catalog'),
                service('tito10047_geo.geocoder.reranker'),
                service('tito10047_geo.geocoder.label_formatter'),
                service('tito10047_geo.geocoder.query_parser'),
                abstract_arg('rerank window'),
            ])
        ->alias(GeocoderInterface::class, 'tito10047_geo.geocoder')

        // the configured defaults of a search, for the commands and the autocompleter
        ->set('tito10047_geo.geocoder.search_options', SearchOptions::class)
            ->args([null, abstract_arg('limit'), abstract_arg('preferred country')])

        // the index: its naming, its manager (needs the HTTP layer, so a "url")
        ->set('tito10047_geo.geocoder.index_naming', TimestampIndexNaming::class)
            ->args([abstract_arg('live index uid')])
        ->alias(IndexNamingInterface::class, 'tito10047_geo.geocoder.index_naming')

        ->set('tito10047_geo.geocoder.indexing_options', IndexingOptions::class)

        ->set('tito10047_geo.geocoder.index_manager', MeilisearchIndexManager::class)
            ->args([service('tito10047_geo.geocoder.meilisearch.http'), service('tito10047_geo.geocoder.indexing_options')])
        ->alias(IndexManagerInterface::class, 'tito10047_geo.geocoder.index_manager')

        // what an import selects, for the import of a next version
        ->set('tito10047_geo.geocoder.selection_rules', SelectionRules::class)
            ->args([
                abstract_arg('place types'),
                abstract_arg('points of interest'),
                abstract_arg('rank of a point of interest'),
                abstract_arg('rank of a street'),
                abstract_arg('languages'),
                abstract_arg('distance of duplicate places'),
            ])

        // commands
        ->set('tito10047_geo.geocoder.command.search', GeocoderSearchCommand::class)
            ->args([service('tito10047_geo.geocoder'), service('tito10047_geo.geocoder.search_options')])
            ->tag('console.command', ['command' => 'tito10047-geo:geocoder:search'])

        ->set('tito10047_geo.geocoder.command.status', GeocoderStatusCommand::class)
            ->args([service('tito10047_geo.geocoder.index_manager'), abstract_arg('live index uid')])
            ->tag('console.command', ['command' => 'tito10047-geo:geocoder:status'])
    ;
};
