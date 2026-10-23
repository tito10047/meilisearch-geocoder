# Routing

Driving routes from a self-hosted [Valhalla](https://github.com/valhalla/valhalla): distance,
duration, geometry, the countries crossed and the tolled distance in each, without a framework.

[![CI](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml/badge.svg)](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml)

Read-only split of the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder)
monorepo: report issues and send pull requests there. Symfony applications should install
[tito10047/geo-bundle](https://github.com/tito10047/geo-bundle) instead, which
configures this library.

## Requirements

- PHP 8.2 or higher
- a [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and
  [PSR-17](https://www.php-fig.org/psr/psr-17/) factories, e.g. `symfony/http-client` and
  `nyholm/psr7`
- a Valhalla server with country borders (`admins.sqlite`), see [Valhalla in Docker](resources/docker/README.md)

## Installation

```console
composer require tito10047/routing symfony/http-client nyholm/psr7
```

## Usage

```php
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Tito10047\Routing\Coordinates;
use Tito10047\Routing\Exception\RouteNotFoundException;
use Tito10047\Routing\Exception\RoutingUnavailableException;
use Tito10047\Routing\RouteRequest;
use Tito10047\Routing\Valhalla\ValhallaRouter;

// PSR-18 has no timeout of its own: set it on the client
$client = new Psr18Client(HttpClient::create(['timeout' => 5]));
// the client is its own PSR-17 factory; a PSR-3 logger may follow
$router = new ValhallaRouter('http://localhost:8002', $client, $client, $client);

$bratislava = new Coordinates(48.1486, 17.1077);
$split = new Coordinates(43.5081, 16.4402);

try {
    $routes = $router->route(new RouteRequest([$bratislava, $split], alternatives: 3));
} catch (RouteNotFoundException) {
    // no road connects the points
} catch (RoutingUnavailableException) {
    // Valhalla is down, failing or has no data there: ask another router
}

$route = $routes[0];                // the best route first
$route->distanceMeters;             // 800560
$route->durationSeconds;            // 26436
$route->geometry;                   // list<Coordinates>, the whole line
$route->distanceByCountry();        // ['SK' => 20110, 'HU' => 247203, 'SI' => 16811, 'HR' => 516436]
$route->tolledByCountry();          // ['SK' => 15051, 'HU' => 162683, 'SI' => 16307, 'HR' => 477321]
$route->countries;                  // SK, HU, SI, HU, HR: a country entered twice is listed twice
```

Without a client and factories, `ValhallaRouter` discovers installed ones
([php-http/discovery](https://docs.php-http.org/en/latest/discovery.html)).

### The request

| Argument | Default | |
|---|---|---|
| `points` | | start, stops, destination: at least two |
| `alternatives` | 1 | how many routes at most; more than one only between two points (Valhalla computes no alternatives with stops) |
| `costing` | `Costing::Car` | also `Truck`, `Bicycle`, `Pedestrian` |
| `radiusMeters` | 200 | how far a point may snap to a road: with 0, a point in a pedestrian zone (Stephansplatz in Vienna) has no route |
| `avoidTolls` | false | prefer roads without tolls; a preference, not a ban (Valhalla's `use_tolls: 0`) |
| `avoidHighways` | false | prefer roads other than motorways; a preference (`use_highways: 0`) |

### Countries and tolls

Valhalla reports the borders a route crosses (`admin_crossings`) and marks the maneuvers on
tolled roads (`toll`). The router places every point of the route by its distance from the
start, puts each border in the middle of the edge Valhalla says it lies on, and cuts the tolled
maneuvers at the borders, so a motorway crossing a border is split between the two countries.
The distances per country add up to the length of the route. "Tolled" is what OpenStreetMap
tags as `toll=yes`: motorways with a vignette count as tolled as well.

### Errors

| Valhalla says | Exception |
|---|---|
| 442 "No path could be found", 441 "Location is unreachable", 170 "Locations are in unconnected regions" | `RouteNotFoundException` |
| 171 "No suitable edges near location" (a point outside the data), any other error, 5xx, an answer that is not a route, a network error or a timeout | `RoutingUnavailableException` |

Every exception implements `Tito10047\Routing\Exception\ExceptionInterface`; the original
exception of the HTTP client is kept as the previous one.

### Extension

`ValhallaRouter` takes its collaborators through interfaces in `Tito10047\Routing\Valhalla`:
`RequestEncoderInterface` (the JSON body), `ResponseParserInterface` (the routes), and
`ErrorTranslatorInterface` (which exception an error is). `ResponseParser` itself takes a
`PolylineDecoderInterface`, a `CountryLocatorInterface` and a `TollLocatorInterface`. Another
engine is another `RouterInterface`.

## Data

Valhalla builds its routing tiles from OpenStreetMap data, © OpenStreetMap contributors,
available under the [Open Database License](https://www.openstreetmap.org/copyright). The
library contains no map data; applications showing routes must attribute OpenStreetMap.

## Development

The library is developed in the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder)
monorepo: install the dependencies and run the checks from its root (see its README). The tests
use answers recorded from a local Valhalla (`tests/Fixtures/valhalla`); the integration tests
ask a live one when `VALHALLA_URL` is set:

```console
VALHALLA_URL=http://127.0.0.1:8002 vendor/bin/phpunit --testsuite routing
```

The changes of each version are listed in the [changelog](CHANGELOG.md).

## License

The routing client is released under the MIT License, see the [LICENSE](LICENSE) file.
