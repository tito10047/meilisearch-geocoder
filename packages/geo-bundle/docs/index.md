# Tito10047GeoBundle

Self-hosted geocoding, address autocomplete and routing for Symfony: OpenStreetMap data on
Meilisearch and Valhalla. The bundle configures the two libraries, usable without Symfony:

- [tito10047/geocoder](https://github.com/tito10047/geocoder): addresses, streets, places and points
  of interest searched in a Meilisearch index, house numbers and translated names in an SQL database;
- [tito10047/routing](https://github.com/tito10047/routing): driving routes from a Valhalla server,
  with the countries crossed and the tolled distance in each.

Each part is a section of the configuration, enabled by configuring it: an application wanting
only addresses does not configure the routing, and the other way round.

## Installation

Follow the [installation instructions](../README.md#installation) of the README.

The Valhalla client needs `ext-curl` (or `amphp/http-client`), see [HTTP clients](#http-clients).

## Configuration

A typical configuration:

```yaml
# config/packages/geo.yaml
geo:
    geocoder:
        meilisearch:
            url: '%env(MEILISEARCH_URL)%'
            api_key: '%env(MEILISEARCH_API_KEY)%'
        database:
            dsn: 'sqlite:%kernel.project_dir%/var/geo/geo.sqlite'
        search:
            preferred_country: 'SK'
    routing:
        url: '%env(VALHALLA_URL)%'
```

### Full reference

```yaml
geo:
    # Addresses: search (autocomplete) over a Meilisearch index and the house numbers
    # and translated names in an SQL database.
    geocoder:
        enabled: false                  # true as soon as the section is configured

        # Give the "url" (and "api_key"), or the id of an existing Meilisearch\Client service as "client".
        meilisearch:
            # The bundle builds the search client and the HTTP layer of the index manager from it.
            url: null                   # e.g. '%env(MEILISEARCH_URL)%'
            # A search key is enough for searching; the index manager needs the master key or an admin key.
            api_key: null               # e.g. '%env(MEILISEARCH_API_KEY)%'
            # An existing Meilisearch\Client service used for searching instead of one built from "url".
            # Without "url" the index manager is not available, nor what needs it: importing into
            # Meilisearch, publishing, artifacts, cleanup and status (exports and installing artifacts remain).
            client: null                # e.g. 'meilisearch.client'
            # A PSR-18 client service sending the requests built from "url", instead of the default one.
            http_client: null
            # Seconds without data after which a request fails (the default HTTP client only), >= 0.1.
            timeout: 5.0
            # Whether to accept gzip answers (the default HTTP client only), see "HTTP clients".
            compression: false
            # The uid of the live index searched; an import builds "<index>_<timestamp>" and swaps it in.
            index: geo
            # The snapshot directory of the instance (MEILI_SNAPSHOT_DIR) as a local path: the artifact
            # command picks the snapshot up there. Without it no artifact can be built.
            snapshot_dir: null          # e.g. '%kernel.project_dir%/var/meilisearch/snapshots'
            # The version of the instance an artifact is installed for; read from the running
            # instance ("url") when null. Overridden by install-artifact --meilisearch-version.
            version: null               # e.g. '1.35.1'

        # Give a "dsn" (connected lazily, on the first query), or the id of an existing PDO service as "connection".
        database:
            dsn: null                   # e.g. 'sqlite:%kernel.project_dir%/var/geo/geo.sqlite'
            user: null
            password: null
            connection: null            # e.g. 'app.geo_pdo'
            tables:                     # letters, digits and underscores, at most 48 characters
                house_numbers: geo_house
                translations: geo_name
                metadata: geo_meta

        # What an import of Photon dumps selects (SelectionRules of tito10047/geocoder).
        import:
            # The dumps (*.jsonl.zst, *.jsonl) the import command imports when it is given none.
            dumps_dir: null             # e.g. '%kernel.project_dir%/var/geo/dumps'
            # ISO 639 codes (lower case) of the translations kept.
            languages: [sk, en, de, hu, cs, pl, hr, sl, it]
            # OSM values of the key "place" imported as places, with their base rank (>= 0).
            place_types:
                city: 90
                town: 70
                village: 50
                suburb: 45
                borough: 45
                quarter: 40
                neighbourhood: 30
                hamlet: 30
            # Points of interest: OSM key => its OSM values. Replaces the defaults as a whole.
            pois:
                tourism: [hotel, guest_house, hostel, motel, apartment, chalet, alpine_hut, camp_site,
                          caravan_site, attraction, museum, theme_park, zoo, aquarium]
                leisure: [water_park, stadium, resort, marina, golf_course]
                aeroway: [aerodrome]
                railway: [station]
                amenity: [hospital, university, college, ferry_terminal, bus_station]
                historic: [castle]
            poi_rank: 20
            street_rank: 25
            place_dedup_km: 5.0         # places of the same name and country closer than this are one
            # Values of the OSM key "highway" that are no streets even when named. Replaces the defaults as a whole.
            street_excluded_highways: [bus_stop, platform, stop, crossing, traffic_signals, street_lamp, milestone,
                                       give_way, turning_circle, turning_loop, mini_roundabout, speed_camera, elevator,
                                       emergency_access_point, motorway_junction, rest_area, services, toll_gantry,
                                       trailhead, via_ferrata, raceway, proposed, emergency_bay, passing_place,
                                       stop_line, traffic_mirror]
            # The "extra" tag holding the official id of an address (Suggestion::$reference), by country.
            # Replaces the defaults as a whole.
            reference_keys:
                SK: 'ref:minvskaddress'   # the id of the Slovak register of addresses
            # The regions the download command downloads into "dumps_dir".
            regions: []                 # e.g. [europe/slovakia, europe/czech-republic]
            # The base URL of the dumps, laid out as GraphHopper's:
            # <url>/<region>/photon-dump-<name>-1.0-latest.jsonl.zst
            download_url: 'https://download1.graphhopper.com/public/'
            # A PSR-18 client service downloading the dumps, instead of the default one (Symfony
            # HttpClient, no limit on the whole download, 60 s without data).
            download_http_client: null

        # The defaults of a search: the commands and the autocompleter use them; code calling
        # GeocoderInterface passes its own SearchOptions.
        search:
            default_limit: 5            # 1 to 20
            preferred_country: null     # ISO 3166-1 alpha-2, upper case, e.g. 'SK'
            # How much higher a foreign suggestion may rank and still be overtaken by one of the
            # preferred country: a home village beats a foreign small town, never a city.
            max_rank_gap: 25000
            rerank_window: 15           # documents fetched for the reranker when a country is preferred

        # The autocompleter of symfony/ux-autocomplete (3.5 or newer). A boolean or "auto" is a shorthand for "enabled".
        ux_autocomplete:
            enabled: auto               # auto: when installed; true: required; false: never
            alias: geo_address

        # An HTTP API answering like the search of Photon ("GET <prefix>/api?q=…"). A boolean is a shorthand for "enabled".
        photon_api:
            enabled: false
            max_age: 3600               # seconds of "Cache-Control: public, max-age"; 0: no caching headers

    # Driving routes from a self-hosted Valhalla.
    routing:
        enabled: false                  # true as soon as the section is configured
        url: ~                          # required, e.g. '%env(VALHALLA_URL)%'
        timeout: 10.0                   # seconds a route may take, connecting included (default HTTP client only), >= 0.1
        radius: 200                     # metres a point may snap to a road (the route and matrix commands)
        max_matrix_location_pairs: 2500 # sources × targets of a matrix, Valhalla's max_matrix_location_pairs, >= 1
        http_client: null               # a PSR-18 client service instead of the default one
```

The values are validated when the container is built: country codes in upper case, languages in
lower case, limits and ranks in their ranges, valid index uids and table names, and the combinations
(`url` or `client`, `dsn` or `connection`, …).

## Usage

### Searching addresses

Inject `Tito10047\Geocoder\Search\GeocoderInterface`:

```php
use Tito10047\Geocoder\Exception\GeocoderUnavailableException;
use Tito10047\Geocoder\Search\GeocoderInterface;
use Tito10047\Geocoder\Search\SearchOptions;

final class AddressController
{
    public function __construct(private readonly GeocoderInterface $geocoder)
    {
    }

    public function suggest(string $text, string $locale): array
    {
        try {
            return $this->geocoder->suggest($text, new SearchOptions(locale: $locale, limit: 5, preferredCountry: 'SK'));
        } catch (GeocoderUnavailableException) {
            return [];
        }
    }
}
```

Each `Suggestion` has an `id`, a `kind` (place, street, address, poi), a `name` and a `label` in
the requested language, `coordinates`, a `countryCode`, and the town, region, postcode, street and
house number when known.

### Routes

Inject `Tito10047\Routing\RouterInterface`:

```php
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\RouteRequest;
use Tito10047\Routing\RouterInterface;

$routes = $router->route(new RouteRequest([new Coordinates(48.1486, 17.1077), new Coordinates(48.7164, 21.2611)], alternatives: 3));
$routes[0]->distanceMeters;
$routes[0]->tolledByCountry(); // ['SK' => 312000]
```

`RouteNotFoundException` means no route goes through the points, `RoutingUnavailableException`
that Valhalla cannot answer now.

### Distance matrix

Inject `Tito10047\Routing\MatrixInterface` for the distance and duration from every source to
every target (on the same Valhalla and HTTP client as the routes):

```php
use Tito10047\Routing\MatrixInterface;
use Tito10047\Routing\MatrixRequest;

$matrix = $this->matrix->matrix(new MatrixRequest([$home], $stations));
$matrix->distanceMeters(0, 2);  // metres from $home to $stations[2], null without a route
$matrix->nearestTarget(0);      // the station reached soonest, or null
```

A pair without a route is null; a matrix larger than `max_matrix_location_pairs` is an
`InvalidArgumentException` before Valhalla is asked; points farther apart than Valhalla's
`max_matrix_distance` (400 km by car by default, in a straight line) or outside its data are a
`RoutingUnavailableException`. See the [routing README](https://github.com/tito10047/routing#distance-matrix).

### Address autocomplete with Symfony UX

With `symfony/ux-autocomplete` 3.5 or newer installed, the bundle registers an autocompleter with
the alias `geo_address` (`ux_autocomplete.alias`). Each result has the label as `text`
and `latitude,longitude|label` as `value`, e.g. `48.1485965,17.1077477|Bratislava, Slovensko`, so
that the form gets the chosen place without a second lookup.

```php
use Symfony\Component\Form\Extension\Core\Type\TextType;

$builder->add('destination', TextType::class, [
    'autocomplete' => true,
    'autocomplete_url' => $this->urlGenerator->generate('ux_autocomplete', [
        'alias' => 'geo_address',
        'locale' => 'sk',
        'country' => 'SK,CZ',
    ]),
]);
```

The query string may narrow the search: `locale` (the language of the labels) and `country` (a
comma-separated list, or `country[]=`, of ISO 3166-1 alpha-2 codes); an invalid one is a 400.
There is a single page of results; the limit and the preferred country are those of `search`.
Everyone may search: protect the route `ux_autocomplete` in the security configuration and rate
limit it when needed.

### Photon-compatible HTTP API

Clients of [Photon](https://github.com/komoot/photon) (e.g. `geocoder-php/photon-provider`, Leaflet
and OpenLayers Photon plugins) can use the geocoder through `GET /api`, the search of Photon 1.3.
Enable it and import the routes of the bundle under a prefix of your choice:

```yaml
# config/packages/geo.yaml
geo:
    geocoder:
        photon_api: true                # or { max_age: 600 }

# config/routes/geo.yaml
geo:
    resource: '@Tito10047GeoBundle/config/routes.php'
    prefix: /photon                     # the clients get "https://example.com/photon" as the Photon URL
```

The routes file may stay imported whatever the configuration: while the API is disabled (or the
geocoder is), it adds no route. The route is `geo_photon_api` (GET and HEAD).

```console
$ curl 'https://example.com/photon/api?q=Hlavn%C3%A1+12+Ko%C5%A1ice&lang=sk&limit=1'
{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"osm_key":"place",
"osm_value":"house","type":"house","housenumber":"12","street":"Hlavná","city":"Košice",
"state":"Košický kraj","country":"Slovensko","postcode":"040 01","countrycode":"SK"},
"geometry":{"type":"Point","coordinates":[21.2580166,48.7189389]}}]}
```

```php
use Geocoder\Provider\Photon\Photon;
use Geocoder\Query\GeocodeQuery;

$provider = new Photon($psr18Client, 'https://example.com/photon');
$addresses = $provider->geocodeQuery(GeocodeQuery::create('Hlavná 12 Košice')->withLocale('sk'));
```

#### Parameters

Every parameter of Photon's `/api` is accepted; an unknown one is a 400, as in Photon. The
filters that repeat in Photon (`layer`, `osm_tag`, `countrycode`, `include`, `exclude`) are read
from the raw query string, so `layer=city&layer=street` works (no `layer[]=`).

| Parameter | Behaviour |
|---|---|
| `q` | required (a search by `include` categories only is not supported) |
| `lang` | the language of the names: an ISO 639 code (`sk`, `sk_SK`) or `default` (the local names); without it the `Accept-Language` header, as in Photon. A language without translations gives the local names. |
| `limit` | 1–20 (`geocoder.search.default_limit` when absent); a smaller one gives 1, a larger one 20, as Photon clamps |
| `countrycode` | ISO 3166-1 alpha-2 (any case), repeatable: only these countries |
| `layer` | `house` (addresses and points of interest), `street`, `city` (city, town), `district` (village, suburb, borough, hamlet), `locality` (quarter, neighbourhood, other places); `county`, `state`, `country` and `other` are valid but match nothing (the index has no such objects); an unknown layer is a 400 |
| `osm_tag` | the six forms of Photon (`key:value`, `key`, `:value`, `!key:value`, `!key`, `:!value`), repeatable, on the tags of the features (see below); a malformed one is a 400 |
| `include`, `exclude` | categories of the group `osm` (`osm.tourism.hotel`, `osm.place`); other groups match nothing |
| `bbox` | `minLon,minLat,maxLon,maxLat`: only the features in the box |
| `lat`, `lon`, `zoom`, `location_bias_scale` | validated (both `lat` and `lon`, numbers in range) and **ignored**: the geocoder has no location bias; `geocoder.search.preferred_country` applies instead |
| `dedupe`, `geometry`, `debug`, `suggest_addresses` | accepted and ignored (the import deduplicates; the geometries are points) |

`layer`, `osm_tag`, `include`, `exclude` and `bbox` filter the 20 best suggestions (the kinds of
the layers are passed to the geocoder): a filtered search may return fewer features than its
limit, never one outside its filters.

#### Answers

A GeoJSON `FeatureCollection` of points with Photon's properties, in Photon's order; what the index
does not know is left out, as Photon leaves out what an object does not have:

| Property | Value |
|---|---|
| `osm_type`, `osm_id` | `N`/`W`/`R` and the id of places and points of interest; **absent** for streets (merged from their ways) and addresses (a house number of a street) |
| `osm_key`, `osm_value` | `place` and the type for places; `place`/`house` for addresses; the key of `import.pois` listing the type and the type for points of interest; `highway` without a value for streets (`geocoder-php/photon-provider` then has no OSM tag) |
| `type` | the layer, as above |
| `name` | except for addresses, as Photon names only named houses |
| `housenumber`, `street` | of addresses |
| `postcode`, `city`, `state` (the region), `countrycode` | as in the suggestions (no `city` for a city itself) |
| `country` | the name in the language of the request; absent for the local names (not stored) |
| `county`, `district`, `locality`, `extent`, `extra` | never |

Errors are `{"message": "…"}` with 400 for an invalid parameter (Photon 1.3 answers some of them
as `{"<parameter>": [{"message": …}]}`; clients only check the status) and 503 when Meilisearch or
the database cannot answer. Only `/api` is provided: no `/reverse`, `/structured` nor `/status`.

The answers have `Cache-Control: public, max-age=<max_age>` (errors `no-store`), `Vary:
Accept-Language` and the OpenStreetMap attribution as
`Link: <https://www.openstreetmap.org/copyright>; rel="license"`; the body has Photon's shape only.
**No CORS headers**: add them for the origins you serve (e.g. `nelmio/cors-bundle` on the prefix).
Access control and rate limiting are the application's too.

The core of the API is framework-free (`Tito10047\Geocoder\Photon\PhotonQuery::fromQueryString()`,
`PhotonSearchInterface`, `FeatureFactoryInterface`): the services
`geo.geocoder.photon_api.search` and `….feature_factory` can be decorated or replaced.

### Services

Every service is private, with an id prefixed by `geo.`, and aliased to its interface for
autowiring. Each collaborator is a service of its own, so that an application can decorate or
replace it (e.g. `#[AsDecorator('geo.geocoder.label_formatter')]`).

| Interface (alias) | Service id | Class |
|---|---|---|
| `Geocoder\Search\GeocoderInterface` | `geo.geocoder` | `Geocoder` |
| `Geocoder\Search\AddressLookupInterface` | `geo.geocoder.address_lookup` | `AddressLookup`: an address by its official id (`findByReference()`) |
| `Geocoder\Search\Backend\SearchBackendInterface` | `geo.geocoder.search_backend` | `MeilisearchSearchBackend` |
| `Geocoder\HouseNumber\HouseNumberStoreInterface` | `geo.geocoder.house_number_store` | `PdoHouseNumberStore` |
| `Geocoder\Name\NameCatalogInterface` | `geo.geocoder.name_catalog` | `PdoNameCatalog` |
| `Geocoder\Search\RerankerInterface` | `geo.geocoder.reranker` | `Reranker` |
| `Geocoder\Search\LabelFormatterInterface` | `geo.geocoder.label_formatter` | `LabelFormatter` |
| `Geocoder\Search\Query\QueryParserInterface` | `geo.geocoder.query_parser` | `QueryParser` |
| `Geocoder\Search\Text\NameNormalizerInterface` | `geo.geocoder.name_normalizer` | `NameNormalizer` |
| `Geocoder\Index\IndexManagerInterface` | `geo.geocoder.index_manager` | `MeilisearchIndexManager` (with a `url` only) |
| `Geocoder\Index\IndexNamingInterface` | `geo.geocoder.index_naming` | `TimestampIndexNaming` |
| | `geo.geocoder.meilisearch.client` | `Meilisearch\Client` |
| | `geo.geocoder.meilisearch.http` | `Meilisearch\Http\Client` (with a `url` only) |
| | `geo.geocoder.meilisearch.http_client` | PSR-18 client of Meilisearch |
| | `geo.geocoder.connection` | `PDO` (lazy) |
| | `geo.geocoder.tables` | `Storage\TableNames` |
| | `geo.geocoder.search_options` | `SearchOptions`: the configured defaults |
| | `geo.geocoder.selection_rules` | `Import\SelectionRules` |
| `Geocoder\Import\ImporterInterface` | `geo.geocoder.importer` | `Importer` (with a `url` only) |
| `Geocoder\Import\ConverterInterface` | `geo.geocoder.import.converter` | `File\ExportAwareConverter`: exports through `…import.export_converter` (`FileExportConverter`), dumps through `…import.photon_converter` (`PhotonConverter` with the `import` rules) |
| `Geocoder\Import\File\FileExporterInterface` | `geo.geocoder.file_exporter` | `FileExporter` |
| `Geocoder\Import\ClockInterface` | `geo.geocoder.import.clock` | `SystemClock` (names the builds) |
| `Geocoder\Import\Download\DumpDownloaderInterface` | `geo.geocoder.import.dump_downloader` | `HttpDumpDownloader` over `…import.download_http_client` |
| `Geocoder\Import\Download\RegionUrlResolverInterface` | `geo.geocoder.import.region_url_resolver` | `GraphHopperRegionUrlResolver` with the `download_url` |
| `Geocoder\Storage\BuildStorageInterface` | `geo.geocoder.build_storage` | `PdoBuildStorage` (lazy) |
| `Geocoder\Storage\Pdo\Dialect\SqlDialectInterface` | `geo.geocoder.sql_dialect` | by the driver of the connection (lazy) |
| `Geocoder\Artifact\ArtifactBuilderInterface` | `geo.geocoder.artifact_builder` | `ArtifactBuilder` (lazy; with a `url` and a `snapshot_dir` only) |
| `Geocoder\Artifact\ArtifactInstallerInterface` | `geo.geocoder.artifact_installer` | `ArtifactInstaller` (lazy) |
| `GeoBundle\Artifact\ArtifactInstallerFactoryInterface` | `geo.geocoder.artifact_installer_factory` | `ArtifactInstallerFactory`: the installer of a Meilisearch version |
| | `geo.geocoder.index_settings` | `Index\IndexSettings` of the builds |
| | `geo.geocoder.autocompleter` | `GeocoderAutocompleter` |
| `Routing\RouterInterface` | `geo.routing.router` | `Valhalla\ValhallaRouter` |
| `Routing\MatrixInterface` | `geo.routing.matrix` | `Valhalla\ValhallaMatrix` |
| `Routing\Valhalla\MatrixRequestEncoderInterface` | `geo.routing.matrix_request_encoder` | `MatrixRequestEncoder` |
| `Routing\Valhalla\MatrixResponseParserInterface` | `geo.routing.matrix_response_parser` | `MatrixResponseParser` |
| | `geo.routing.matrix_error_translator` | `ErrorTranslator::forMatrix()` |
| `Routing\Valhalla\RequestEncoderInterface` | `geo.routing.request_encoder` | `RequestEncoder` |
| `Routing\Valhalla\ResponseParserInterface` | `geo.routing.response_parser` | `ResponseParser` |
| `Routing\Valhalla\ErrorTranslatorInterface` | `geo.routing.error_translator` | `ErrorTranslator` |
| `Routing\Geometry\PolylineDecoderInterface` | `geo.routing.polyline_decoder` | `PolylineDecoder` |
| `Routing\Valhalla\CountryLocatorInterface` | `geo.routing.country_locator` | `AdminCrossingCountryLocator` |
| `Routing\Valhalla\TollLocatorInterface` | `geo.routing.toll_locator` | `ManeuverTollLocator` |
| `GeoBundle\Routing\RoutingStatusProviderInterface` | `geo.routing.status_provider` | `ValhallaStatusProvider` |
| | `geo.routing.http_client` | PSR-18 client of Valhalla |

The database connection built from the `dsn` is a lazy service: PDO connects on the first query,
not when the container builds the geocoder or a command; a database that cannot be reached is a
`GeocoderUnavailableException`. The build storage, the SQL dialect and the artifact services are
lazy too, as they ask the connection for its driver when they are built. `PdoNameCatalog`
remembers the translations it read; it is tagged `kernel.reset`, so Symfony forgets them between
the requests of a long-running worker (Messenger, RoadRunner, FrankenPHP).

### HTTP clients

The default PSR-18 clients are Symfony HttpClient (`Psr18Client`), with options addressing two
problems met in production:

- **Meilisearch** gzips its answers when the client accepts it, and a compressed answer of a few
  kilobytes (3 to 10 suggestions) arrives about 40 ms late, a TCP delayed-ACK stall, where an
  uncompressed one takes 1 ms. The client of Meilisearch sends `Accept-Encoding: identity`
  (`compression: false`); compressing a 1-20 kB answer saves nothing on a local network.
- **Valhalla** keeps the connection open after an answer: a client reading the body until the
  socket closes (a PHP stream wrapper, e.g. `file_get_contents()`) hangs until its timeout. The
  client of Valhalla is `CurlHttpClient` (or `AmpHttpClient` without `ext-curl`), which reads the
  body by its length and reuses the connection; `timeout` limits the whole request.

- **Downloads of the dumps**: hundreds of megabytes, so no limit on the whole download, only
  60 seconds without data.

Configure `http_client` (`import.download_http_client` for the downloads) with the id of your own
PSR-18 client service to replace them.

## Commands

The commands of a section are registered when the section is enabled.

```console
# the suggestions for a text, as a table
$ php bin/console geo:search "Hlavná 12 Košice" --locale=sk --preferred-country=SK
$ php bin/console geo:search wien --country=AT --country=SK --limit=10

# the live index (documents, sizes, indexing, settings, Meilisearch version) and the live build
# (data timestamp of each country and dump, counts, schema version of the tables)
$ php bin/console geo:status

# routes through points: distance, duration, countries, tolls per country
$ php bin/console geo:route 48.1486,17.1077 48.7164,21.2611 --alternatives=3
$ php bin/console geo:route 48.1486,17.1077 48.2082,16.3738 49.1951,16.6068 --costing=truck --radius=500

# distance and duration from every source to every target, as a table (km, min)
$ php bin/console geo:matrix --source=48.1486,17.1077 --source=48.3774,17.5872 --target=49.2231,18.7394 --target=48.3069,18.0864
$ php bin/console geo:matrix --source=48.1486,17.1077 --target=48.2082,16.3738 --costing=bicycle

# whether Valhalla is reachable, its version and the date of its routing data
$ php bin/console geo:route:status
```

Every command prints the OpenStreetMap attribution, and ends with a message and the exit code 1
(no stack trace) when the search engine, the database, a dump or an artifact fails.

### Downloading the dumps

```console
# the regions of import.regions into import.dumps_dir, e.g. europe/slovakia as slovakia.jsonl.zst
$ php bin/console geo:download
# given regions; --force downloads even a dump that has not changed
$ php bin/console geo:download europe/slovakia europe/austria --force
```

A region is the path of the download server (`europe/slovakia`, `europe/germany/bayern`; see
https://download1.graphhopper.com/public/). A dump is downloaded beside the previous copy, its
Photon header checked (a `.zst` is decompressed at its head only) and only then renamed over it:
an interrupted or failed download leaves the old copy. The `ETag` and `Last-Modified` of each
download are kept beside the dump (`slovakia.jsonl.zst.download.json`), so the next run asks the
server only for a changed dump and skips an unchanged one. Run it from cron before the import,
e.g. weekly (GraphHopper publishes new dumps about once a week):

```console
$ php bin/console geo:download && php bin/console geo:import
```

### Importing the data

An import never touches what searches use: it fills a new index `<index>_<timestamp>` and new
tables `<table>_<timestamp>` (a *build*), and the publication swaps them in at once (Meilisearch
swaps the indexes, the tables are renamed in one go), dropping the replaced ones and older builds.

```console
# the *.jsonl.zst and *.jsonl dumps of import.dumps_dir, published once built
$ php bin/console geo:import
# given dumps (a path, or php://stdin); .zst through ext-zstd or the zstd command
$ php bin/console geo:import var/geo/dumps/slovakia.jsonl.zst var/geo/dumps/czechia.jsonl.zst

# build only, check it, publish it later
$ php bin/console geo:import --no-publish
$ php bin/console geo:publish geo_20261023120000

# remove the builds left by failed, abandoned or never published imports (never while an import runs)
$ php bin/console geo:cleanup [--keep=geo_20261023120000]
```

The import prints a progress bar per dump, then the build: its uid, the documents and house
numbers, the data timestamp and countries of each dump.

**Files only.** `--export=<dir>` converts the dumps into a directory (the documents in NDJSON and
an SQLite database of the tables) without Meilisearch, e.g. on a machine with more memory; give
that directory to `import` on the machine with Meilisearch, like a dump:

```console
build-machine$ php bin/console geo:import --export=var/geo/export
server$        php bin/console geo:import var/geo/export
```

## Deployment modes

Meilisearch loads a snapshot only when it starts **without a database**, and only one made by **its
own version**. Hence two ways to bring the data to a server:

| Mode | When | How |
|---|---|---|
| **Separate instance + artifact** (recommended) | large regions, small server; Meilisearch dedicated to the geocoder | build on a strong machine, install the artifact on the server: a new data directory and a restart of Meilisearch |
| Shared instance, indexing in place | 1–3 small countries, a Meilisearch shared with other projects | `import` (and `publish`) directly on the server |

### Indexing in place

Configure the geocoder on the server and run `geo:import` there (e.g. from a
cron job once the new dumps are downloaded). Indexing takes the memory and CPU of the server while
it runs; searches keep answering from the live index meanwhile.

### Separate instance and artifacts

An *artifact* is a directory with a snapshot of the Meilisearch instance, an SQLite copy of the
live tables and a `manifest.json` (Meilisearch version, settings hash, schema version, data
timestamps, counts, checksums).

On the **build machine** (a Meilisearch holding the index of the geocoder only, as the snapshot
holds every index; its snapshot directory reachable from the application):

1. Configure `meilisearch.snapshot_dir`, e.g. with the instance in Docker:

   ```console
   $ docker run -d --name geo-build -p 127.0.0.1:7700:7700 -e MEILI_MASTER_KEY=… \
       -v "$PWD/var/meilisearch/data:/meili_data" -v "$PWD/var/meilisearch/snapshots:/snapshots" \
       -e MEILI_SNAPSHOT_DIR=/snapshots getmeili/meilisearch:v1.35.1
   ```

   ```yaml
   geo:
       geocoder:
           meilisearch:
               url: 'http://127.0.0.1:7700'
               snapshot_dir: '%kernel.project_dir%/var/meilisearch/snapshots'
   ```

2. Import and publish the dumps: `php bin/console geo:import`.
3. Pack the live build: `php bin/console geo:artifact var/geo/artifact`.
4. Copy the directory to the server (`rsync -a var/geo/artifact server:/srv/geo/`).

On the **server** (Meilisearch of the same version, e.g. a container with its data directory
mounted on `/meili_data`):

5. Install the artifact into a new, empty data directory while the old instance keeps serving:

   ```console
   $ mkdir /srv/meili_data_new
   $ php bin/console geo:artifact:install /srv/geo/artifact /srv/meili_data_new
   ```

   The command verifies the checksums and the versions, copies the snapshot into the data
   directory (`geocoder.snapshot`) and makes the tables live in the database of the application.
   The Meilisearch version is read from the running instance; give it with
   `--meilisearch-version=1.35.1` (or `meilisearch.version`) when the instance is not reachable or
   is upgraded at the same time.

6. Restart Meilisearch on the new data directory with the snapshot, as the command prints, e.g.:

   ```console
   $ docker rm -f geo-meili
   $ docker run -d --name geo-meili -p 127.0.0.1:7700:7700 -e MEILI_MASTER_KEY=… \
       -v /srv/meili_data_new:/meili_data getmeili/meilisearch:v1.35.1 \
       meilisearch --import-snapshot /meili_data/geocoder.snapshot
   ```

7. Check it with `php bin/console geo:status`, then delete the snapshot file and
   the old data directory.

Between the steps 5 and 6 the tables are already the new ones while the index is the old one:
the suggestions keep working, house numbers and translations of changed streets may differ for
that moment.

## OpenStreetMap attribution

The addresses and the routes come from [OpenStreetMap](https://www.openstreetmap.org/), available
under the [Open Database License](https://www.openstreetmap.org/copyright) (ODbL). The libraries
do not distribute any data, but **an application displaying the suggestions or the routes must
credit the contributors**, e.g. under the autocomplete field or the map:

> © OpenStreetMap contributors

The text is available as `Tito10047\GeoBundle\Attribution::OPENSTREETMAP`; the commands print it.
