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
# config/packages/tito10047_geo.yaml
tito10047_geo:
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
tito10047_geo:
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
            # Without "url" the index manager (and the status command) is not available.
            client: null                # e.g. 'meilisearch.client'
            # A PSR-18 client service sending the requests built from "url", instead of the default one.
            http_client: null
            # Seconds without data after which a request fails (the default HTTP client only), >= 0.1.
            timeout: 5.0
            # Whether to accept gzip answers (the default HTTP client only), see "HTTP clients".
            compression: false
            # The uid of the live index searched; an import builds "<index>_<timestamp>" and swaps it in.
            index: geo

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
            alias: tito10047_geo_address

    # Driving routes from a self-hosted Valhalla.
    routing:
        enabled: false                  # true as soon as the section is configured
        url: ~                          # required, e.g. '%env(VALHALLA_URL)%'
        timeout: 10.0                   # seconds a route may take, connecting included (default HTTP client only), >= 0.1
        radius: 200                     # metres a point may snap to a road (the route command)
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

### Address autocomplete with Symfony UX

With `symfony/ux-autocomplete` 3.5 or newer installed, the bundle registers an autocompleter with
the alias `tito10047_geo_address` (`ux_autocomplete.alias`). Each result has the label as `text`
and `latitude,longitude|label` as `value`, e.g. `48.1485965,17.1077477|Bratislava, Slovensko`, so
that the form gets the chosen place without a second lookup.

```php
use Symfony\Component\Form\Extension\Core\Type\TextType;

$builder->add('destination', TextType::class, [
    'autocomplete' => true,
    'autocomplete_url' => $this->urlGenerator->generate('ux_autocomplete', [
        'alias' => 'tito10047_geo_address',
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

### Services

Every service is private, with an id prefixed by `tito10047_geo.`, and aliased to its interface for
autowiring. Each collaborator is a service of its own, so that an application can decorate or
replace it (e.g. `#[AsDecorator('tito10047_geo.geocoder.label_formatter')]`).

| Interface (alias) | Service id | Class |
|---|---|---|
| `Geocoder\Search\GeocoderInterface` | `tito10047_geo.geocoder` | `Geocoder` |
| `Geocoder\Search\Backend\SearchBackendInterface` | `tito10047_geo.geocoder.search_backend` | `MeilisearchSearchBackend` |
| `Geocoder\HouseNumber\HouseNumberStoreInterface` | `tito10047_geo.geocoder.house_number_store` | `PdoHouseNumberStore` |
| `Geocoder\Name\NameCatalogInterface` | `tito10047_geo.geocoder.name_catalog` | `PdoNameCatalog` |
| `Geocoder\Search\RerankerInterface` | `tito10047_geo.geocoder.reranker` | `Reranker` |
| `Geocoder\Search\LabelFormatterInterface` | `tito10047_geo.geocoder.label_formatter` | `LabelFormatter` |
| `Geocoder\Search\Query\QueryParserInterface` | `tito10047_geo.geocoder.query_parser` | `QueryParser` |
| `Geocoder\Search\Text\NameNormalizerInterface` | `tito10047_geo.geocoder.name_normalizer` | `NameNormalizer` |
| `Geocoder\Index\IndexManagerInterface` | `tito10047_geo.geocoder.index_manager` | `MeilisearchIndexManager` (with a `url` only) |
| `Geocoder\Index\IndexNamingInterface` | `tito10047_geo.geocoder.index_naming` | `TimestampIndexNaming` |
| | `tito10047_geo.geocoder.meilisearch.client` | `Meilisearch\Client` |
| | `tito10047_geo.geocoder.meilisearch.http` | `Meilisearch\Http\Client` (with a `url` only) |
| | `tito10047_geo.geocoder.meilisearch.http_client` | PSR-18 client of Meilisearch |
| | `tito10047_geo.geocoder.connection` | `PDO` (lazy) |
| | `tito10047_geo.geocoder.tables` | `Storage\TableNames` |
| | `tito10047_geo.geocoder.search_options` | `SearchOptions`: the configured defaults |
| | `tito10047_geo.geocoder.selection_rules` | `Import\SelectionRules` |
| | `tito10047_geo.geocoder.autocompleter` | `GeocoderAutocompleter` |
| `Routing\RouterInterface` | `tito10047_geo.routing.router` | `Valhalla\ValhallaRouter` |
| `Routing\Valhalla\RequestEncoderInterface` | `tito10047_geo.routing.request_encoder` | `RequestEncoder` |
| `Routing\Valhalla\ResponseParserInterface` | `tito10047_geo.routing.response_parser` | `ResponseParser` |
| `Routing\Valhalla\ErrorTranslatorInterface` | `tito10047_geo.routing.error_translator` | `ErrorTranslator` |
| `Routing\Geometry\PolylineDecoderInterface` | `tito10047_geo.routing.polyline_decoder` | `PolylineDecoder` |
| `Routing\Valhalla\CountryLocatorInterface` | `tito10047_geo.routing.country_locator` | `AdminCrossingCountryLocator` |
| `Routing\Valhalla\TollLocatorInterface` | `tito10047_geo.routing.toll_locator` | `ManeuverTollLocator` |
| `GeoBundle\Routing\RoutingStatusProviderInterface` | `tito10047_geo.routing.status_provider` | `ValhallaStatusProvider` |
| | `tito10047_geo.routing.http_client` | PSR-18 client of Valhalla |

The database connection built from the `dsn` is a lazy service: PDO connects on the first query,
not when the container builds the geocoder. `PdoNameCatalog` remembers the translations it read;
a long-running worker (Messenger, RoadRunner, FrankenPHP) keeps them until it restarts, so restart
the workers after publishing new data.

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

Configure `http_client` with the id of your own PSR-18 client service to replace them.

## Commands

The commands of a section are registered when the section is enabled.

```console
# the suggestions for a text, as a table
$ php bin/console tito10047-geo:geocoder:search "Hlavná 12 Košice" --locale=sk --preferred-country=SK
$ php bin/console tito10047-geo:geocoder:search wien --country=AT --country=SK --limit=10

# the live index: documents, sizes, whether it is being indexed, its settings, the Meilisearch version
$ php bin/console tito10047-geo:geocoder:status

# routes through points: distance, duration, countries, tolls per country
$ php bin/console tito10047-geo:routing:route 48.1486,17.1077 48.7164,21.2611 --alternatives=3
$ php bin/console tito10047-geo:routing:route 48.1486,17.1077 48.2082,16.3738 49.1951,16.6068 --costing=truck --radius=500

# whether Valhalla is reachable, its version and the date of its routing data
$ php bin/console tito10047-geo:routing:status
```

The import of the dumps and the publication of a built index come with the next version.

## OpenStreetMap attribution

The addresses and the routes come from [OpenStreetMap](https://www.openstreetmap.org/), available
under the [Open Database License](https://www.openstreetmap.org/copyright) (ODbL). The libraries
do not distribute any data, but **an application displaying the suggestions or the routes must
credit the contributors**, e.g. under the autocomplete field or the map:

> © OpenStreetMap contributors

The text is available as `Tito10047\GeoBundle\Attribution::OPENSTREETMAP`; the commands print it.
