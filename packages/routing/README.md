# Routing

Driving routes from a self-hosted [Valhalla](https://github.com/valhalla/valhalla): distance,
duration, geometry, the countries crossed and the tolled distance in each, without a framework.

[![CI](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml/badge.svg)](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml)

Read-only split of the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder)
monorepo: report issues and send pull requests there. Symfony applications should install
[tito10047/geo-bundle](https://github.com/tito10047/geo-bundle) instead, which
configures this library.

> Work in progress: the library is not usable yet.

## Requirements

- PHP 8.2 or higher

## Installation

```console
composer require tito10047/routing
```

## Data

Valhalla builds its routing tiles from OpenStreetMap data, © OpenStreetMap contributors,
available under the [Open Database License](https://www.openstreetmap.org/copyright).

## Development

The library is developed in the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder)
monorepo: install the dependencies and run the checks from its root (see its README).

The changes of each version are listed in the [changelog](CHANGELOG.md).

## License

The routing client is released under the MIT License, see the [LICENSE](LICENSE) file.
