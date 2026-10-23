<?php

namespace Tito10047\Geocoder\Search;

use Meilisearch\Client;
use Tito10047\Geocoder\Exception\InvalidArgumentException;
use Tito10047\Geocoder\HouseNumber\PdoHouseNumberStore;
use Tito10047\Geocoder\Name\PdoCountryNameSource;
use Tito10047\Geocoder\Name\PdoNameCatalog;
use Tito10047\Geocoder\Search\Backend\MeilisearchSearchBackend;
use Tito10047\Geocoder\Search\Query\CatalogCountryRecognizer;
use Tito10047\Geocoder\Search\Query\QueryParser;
use Tito10047\Geocoder\Storage\TableNames;

/**
 * Builds the geocoder and the lookup of addresses with the default collaborators, for the
 * applications without a container:
 *
 *     $geocoder = GeocoderFactory::create(new Client('http://127.0.0.1:7700', $key), new \PDO('sqlite:geo.sqlite'));
 *     $lookup = GeocoderFactory::createAddressLookup(new Client('http://127.0.0.1:7700', $key), new \PDO('sqlite:geo.sqlite'));
 *
 * The geocoder recognizes the names of the imported countries in the typed text (QueryParser with
 * CatalogCountryRecognizer). To replace a collaborator, construct Geocoder or AddressLookup directly.
 */
final class GeocoderFactory
{
    /**
     * @param string $indexUid   the Meilisearch index holding the documents
     * @param int    $maxRankGap see Reranker
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public static function create(
        Client $client,
        \PDO $connection,
        string $indexUid = 'geo',
        TableNames $tables = new TableNames(),
        int $maxRankGap = Reranker::DEFAULT_MAX_RANK_GAP,
    ): Geocoder {
        $catalog = new PdoNameCatalog($connection, $tables);

        return new Geocoder(
            new MeilisearchSearchBackend($client, $indexUid),
            new PdoHouseNumberStore($connection, $tables),
            $catalog,
            new Reranker($maxRankGap),
            new LabelFormatter($catalog),
            new QueryParser(new CatalogCountryRecognizer(new PdoCountryNameSource($connection, $tables))),
        );
    }

    /**
     * @param string $indexUid the Meilisearch index holding the documents
     *
     * @throws InvalidArgumentException when a value is invalid
     */
    public static function createAddressLookup(
        Client $client,
        \PDO $connection,
        string $indexUid = 'geo',
        TableNames $tables = new TableNames(),
    ): AddressLookup {
        $catalog = new PdoNameCatalog($connection, $tables);

        return new AddressLookup(
            new PdoHouseNumberStore($connection, $tables),
            new MeilisearchSearchBackend($client, $indexUid),
            $catalog,
            new LabelFormatter($catalog),
        );
    }
}
