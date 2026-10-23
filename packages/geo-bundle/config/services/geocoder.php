<?php

use Meilisearch\Client;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Tito10047\GeoBundle\Command\GeocoderSearchCommand;
use Tito10047\GeoBundle\Command\GeocoderStatusCommand;
use Tito10047\GeoBundle\Database\PdoConnectionFactory;
use Tito10047\GeoBundle\Geocoder\GeocoderServiceFactory;
use Tito10047\GeoBundle\Http\HttpClientFactory;
use Tito10047\Geocoder\HouseNumber\HouseNumberReferenceStoreInterface;
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
use Tito10047\Geocoder\Search\AddressLookup;
use Tito10047\Geocoder\Search\AddressLookupInterface;
use Tito10047\Geocoder\Search\Backend\DocumentSourceInterface;
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
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_closure;

/*
 * The services of the geocoder (tito10047/geocoder), loaded when "geo.geocoder" is
 * enabled. Every collaborator is a service of its own, aliased to its interface, so that an
 * application can decorate or replace any of them. The abstract arguments are set from the
 * configuration by Tito10047GeoBundle::loadExtension().
 *
 * With a "url", the geocoder, the address lookup, the search backend and the index manager are
 * created by GeocoderServiceFactory: the real implementations ("….configured") when the URL is
 * given, UnconfiguredMeilisearch when it resolves to an empty value at runtime. With a "client",
 * they are aliases of the real implementations.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Meilisearch: the search client and the HTTP layer of the index manager share the URL, the key and the PSR-18 client
    $services
        ->set('geo.geocoder.meilisearch.http_client', ClientInterface::class)
            ->factory(HttpClientFactory::class.'::meilisearch')
            ->args([abstract_arg('timeout'), abstract_arg('whether to accept gzip')])

        ->set('geo.geocoder.meilisearch.client', Client::class)
            ->args([
                abstract_arg('url'),
                abstract_arg('API key'),
                service('geo.geocoder.meilisearch.http_client'),
                service('geo.psr17_factory'),
                [],
                service('geo.psr17_factory'),
            ])

        ->set('geo.geocoder.meilisearch.http', Meilisearch\Http\Client::class)
            ->args([
                abstract_arg('url'),
                abstract_arg('API key'),
                service('geo.geocoder.meilisearch.http_client'),
                service('geo.psr17_factory'),
                [],
                service('geo.psr17_factory'),
            ])

        // the database: connected on the first query, not when the container builds the geocoder
        ->set('geo.geocoder.connection', PDO::class)
            ->factory(PdoConnectionFactory::connect(...))
            ->lazy()
            ->args([abstract_arg('DSN'), abstract_arg('user'), abstract_arg('password')])

        ->set('geo.geocoder.tables', TableNames::class)
            ->args([abstract_arg('house numbers'), abstract_arg('translations'), abstract_arg('metadata')])

        // search
        ->set('geo.geocoder.search_backend.configured', MeilisearchSearchBackend::class)
            ->args([service('geo.geocoder.meilisearch.client'), abstract_arg('index uid')])
        ->set('geo.geocoder.search_backend', SearchBackendInterface::class)
            ->factory(GeocoderServiceFactory::searchBackend(...))
            ->args([abstract_arg('url'), service_closure('geo.geocoder.search_backend.configured')])
        ->alias(SearchBackendInterface::class, 'geo.geocoder.search_backend')
        ->alias(DocumentSourceInterface::class, 'geo.geocoder.search_backend')

        ->set('geo.geocoder.house_number_store', PdoHouseNumberStore::class)
            ->args([service('geo.geocoder.connection'), service('geo.geocoder.tables')])
        ->alias(HouseNumberStoreInterface::class, 'geo.geocoder.house_number_store')
        ->alias(HouseNumberReferenceStoreInterface::class, 'geo.geocoder.house_number_store')

        // remembers translations: forgotten between the requests of a long-running worker, and after a publication
        ->set('geo.geocoder.name_catalog', PdoNameCatalog::class)
            ->args([service('geo.geocoder.connection'), service('geo.geocoder.tables')])
            // a decorator of the catalog (e.g. a test fake or an application cache) may have no reset()
            ->tag('kernel.reset', ['method' => 'reset', 'on_invalid' => 'ignore'])
        ->alias(NameCatalogInterface::class, 'geo.geocoder.name_catalog')

        ->set('geo.geocoder.name_normalizer', NameNormalizer::class)
        ->alias(NameNormalizerInterface::class, 'geo.geocoder.name_normalizer')

        ->set('geo.geocoder.reranker', Reranker::class)
            ->args([abstract_arg('maximum rank gap'), service('geo.geocoder.name_normalizer')])
        ->alias(RerankerInterface::class, 'geo.geocoder.reranker')

        ->set('geo.geocoder.label_formatter', LabelFormatter::class)
            ->args([service('geo.geocoder.name_catalog'), service('geo.geocoder.name_normalizer')])
        ->alias(LabelFormatterInterface::class, 'geo.geocoder.label_formatter')

        ->set('geo.geocoder.query_parser', QueryParser::class)
        ->alias(QueryParserInterface::class, 'geo.geocoder.query_parser')

        ->set('geo.geocoder.configured', Geocoder::class)
            ->args([
                service('geo.geocoder.search_backend'),
                service('geo.geocoder.house_number_store'),
                service('geo.geocoder.name_catalog'),
                service('geo.geocoder.reranker'),
                service('geo.geocoder.label_formatter'),
                service('geo.geocoder.query_parser'),
                abstract_arg('rerank window'),
            ])
        ->set('geo.geocoder', GeocoderInterface::class)
            ->factory(GeocoderServiceFactory::geocoder(...))
            ->args([abstract_arg('url'), service_closure('geo.geocoder.configured')])
        ->alias(GeocoderInterface::class, 'geo.geocoder')

        // an address by its official id (ref:minvskaddress in Slovakia)
        ->set('geo.geocoder.address_lookup.configured', AddressLookup::class)
            ->args([
                service('geo.geocoder.house_number_store'),
                service('geo.geocoder.search_backend'),
                service('geo.geocoder.name_catalog'),
                service('geo.geocoder.label_formatter'),
            ])
        ->set('geo.geocoder.address_lookup', AddressLookupInterface::class)
            ->factory(GeocoderServiceFactory::addressLookup(...))
            ->args([abstract_arg('url'), service_closure('geo.geocoder.address_lookup.configured')])
        ->alias(AddressLookupInterface::class, 'geo.geocoder.address_lookup')

        // the configured defaults of a search, for the commands and the autocompleter
        ->set('geo.geocoder.search_options', SearchOptions::class)
            ->args([null, abstract_arg('limit'), abstract_arg('preferred country'), [], [], abstract_arg('complete matches only')])

        // the index: its naming, its manager (needs the HTTP layer, so a "url")
        ->set('geo.geocoder.index_naming', TimestampIndexNaming::class)
            ->args([abstract_arg('live index uid')])
        ->alias(IndexNamingInterface::class, 'geo.geocoder.index_naming')

        ->set('geo.geocoder.indexing_options', IndexingOptions::class)

        ->set('geo.geocoder.index_manager.configured', MeilisearchIndexManager::class)
            ->args([service('geo.geocoder.meilisearch.http'), service('geo.geocoder.indexing_options')])
        ->set('geo.geocoder.index_manager', IndexManagerInterface::class)
            ->factory(GeocoderServiceFactory::indexManager(...))
            ->args([abstract_arg('url'), service_closure('geo.geocoder.index_manager.configured')])
        ->alias(IndexManagerInterface::class, 'geo.geocoder.index_manager')

        // what an import selects, for the import of a next version
        ->set('geo.geocoder.selection_rules', SelectionRules::class)
            ->args([
                abstract_arg('place types'),
                abstract_arg('points of interest'),
                abstract_arg('rank of a point of interest'),
                abstract_arg('rank of a street'),
                abstract_arg('languages'),
                abstract_arg('distance of duplicate places'),
            ])

        // commands
        ->set('geo.geocoder.command.search', GeocoderSearchCommand::class)
            ->args([service('geo.geocoder'), service('geo.geocoder.search_options')])
            ->tag('console.command', ['command' => 'geo:search'])

        ->set('geo.geocoder.command.status', GeocoderStatusCommand::class)
            ->args([service('geo.geocoder.index_manager'), abstract_arg('live index uid'), service('geo.geocoder.importer')->nullOnInvalid()])
            ->tag('console.command', ['command' => 'geo:status'])
    ;
};
