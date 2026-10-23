# Geocoder

Self-hosted geocoder and address autocomplete built from OpenStreetMap data on Meilisearch,
without a framework.

[![CI](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml/badge.svg)](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml)

Read-only split of the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder)
monorepo: report issues and send pull requests there. Symfony applications should install
[tito10047/geocoder-bundle](https://github.com/tito10047/geocoder-bundle) instead, which
configures this library.

> Work in progress: the library is not usable yet.

## Requirements

- PHP 8.2 or higher

## Installation

```console
composer require tito10047/geocoder
```

## Data

The geocoder is built from the weekly [Photon](https://github.com/komoot/photon) dumps of
OpenStreetMap data. The library downloads them, it does not distribute them: the data is
© OpenStreetMap contributors, available under the
[Open Database License](https://www.openstreetmap.org/copyright), and so is the index built
from it. Show the attribution wherever its results are displayed.

## Development

The library is developed in the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder)
monorepo, together with its Symfony bundle: install the dependencies and run the checks from its
root (see its README).

The changes of each version are listed in the [changelog](CHANGELOG.md).

## License

The geocoder is released under the MIT License, see the [LICENSE](LICENSE) file.
