# Tito10047GeoBundle

Self-hosted geocoding, address autocomplete and routing for Symfony: OpenStreetMap data on Meilisearch and Valhalla

> Work in progress (0.1): searching addresses, importing the data, artifacts, routes, the
> commands and the UX Autocomplete integration work; the API may still change.

[![CI](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml/badge.svg)](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml)

Read-only split of the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder) monorepo: report issues and send pull requests there. The libraries it configures, usable without Symfony, are [tito10047/geocoder](https://github.com/tito10047/geocoder) and [tito10047/routing](https://github.com/tito10047/routing).

## Requirements

- PHP 8.2 or higher (8.4 or higher with Symfony 8)
- Symfony 7.4 LTS or 8.1 and higher
- Meilisearch 1.18 or higher (tuned on 1.35.1) for the addresses, Valhalla for the routes
- `ext-curl` (or `amphp/http-client`) for the routes
- optionally `symfony/ux-autocomplete` 3.5 or higher (PHP 8.4) for the autocomplete field

## Installation

Make sure Composer is installed globally, as explained in the
[installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Applications that use Symfony Flex

Open a command console, enter your project directory and execute:

```console
composer require tito10047/geo-bundle
```

### Applications that don't use Symfony Flex

#### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
composer require tito10047/geo-bundle
```

#### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Tito10047\GeoBundle\Tito10047GeoBundle::class => ['all' => true],
];
```

## Configuration

Configure the parts you need; each section is enabled by configuring it:

```yaml
# config/packages/tito10047_geo.yaml
tito10047_geo:
    geocoder:
        meilisearch:
            url: '%env(MEILISEARCH_URL)%'
            api_key: '%env(MEILISEARCH_API_KEY)%'
        database:
            dsn: 'sqlite:%kernel.project_dir%/var/geo/geo.sqlite'
    routing:
        url: '%env(VALHALLA_URL)%'
```

Then inject `Tito10047\Geocoder\Search\GeocoderInterface` or `Tito10047\Routing\RouterInterface`,
or try them from the console:

```console
php bin/console tito10047-geo:geocoder:search "Hlavná 12 Košice" --locale=sk
php bin/console tito10047-geo:routing:route 48.1486,17.1077 48.7164,21.2611
```

## Photon-compatible API

`GET /api?q=…` answering like [Photon](https://github.com/komoot/photon) (GeoJSON, `lang`, `limit`,
`layer`, `osm_tag`, `countrycode`, `bbox`, …), so that Photon clients such as
`geocoder-php/photon-provider` work against your own data:

```yaml
# config/packages/tito10047_geo.yaml
tito10047_geo:
    geocoder:
        photon_api: true

# config/routes/tito10047_geo.yaml
tito10047_geo:
    resource: '@Tito10047GeoBundle/config/routes.php'
    prefix: /photon
```

See [Photon-compatible HTTP API](docs/index.md#photon-compatible-http-api) for the parameters, the
properties and what differs from Photon.

## Commands

```console
# addresses: import the Photon dumps (of import.dumps_dir by default) and publish them
php bin/console tito10047-geo:geocoder:import [dump…] [--no-publish] [--export=<dir>]
php bin/console tito10047-geo:geocoder:publish <build-uid>
php bin/console tito10047-geo:geocoder:cleanup [--keep=<build-uid>]
php bin/console tito10047-geo:geocoder:artifact <dir>
php bin/console tito10047-geo:geocoder:install-artifact <dir> <meilisearch-data-dir> [--meilisearch-version=1.35.1]
php bin/console tito10047-geo:geocoder:status
php bin/console tito10047-geo:geocoder:search "Hlavná 12 Košice" [--locale=sk] [--country=SK] [--preferred-country=SK] [--limit=5]

# routes
php bin/console tito10047-geo:routing:route 48.1486,17.1077 48.7164,21.2611 [--alternatives=3]
php bin/console tito10047-geo:routing:status
```

An import builds a new index and new tables while searches keep using the live ones; the
publication swaps them in at once. `--export` converts the dumps into files only (no Meilisearch),
to be imported on another machine by giving that directory to `import`.

## Deployment

Meilisearch loads a snapshot only when it starts without a database, and only one of its own
version. So there are two ways to bring the data to a server:

| Mode | When | How |
|---|---|---|
| **Separate instance + artifact** (recommended) | large regions, small server | build on a strong machine; on the server a new data directory and a restart of Meilisearch |
| Shared instance, indexing in place | 1–3 small countries, a Meilisearch shared with other projects | `tito10047-geo:geocoder:import` directly on the server |

The artifact mode, step by step:

1. **Build machine**: a Meilisearch holding the geocoder only (the snapshot holds every index),
   its snapshot directory (`MEILI_SNAPSHOT_DIR`) configured as `geocoder.meilisearch.snapshot_dir`.
2. `php bin/console tito10047-geo:geocoder:import` — imports and publishes the dumps.
3. `php bin/console tito10047-geo:geocoder:artifact var/geo/artifact` — the snapshot, an SQLite
   copy of the tables and a `manifest.json` (versions, data timestamps, checksums).
4. Copy the directory to the server.
5. **Server**: `php bin/console tito10047-geo:geocoder:install-artifact /srv/geo/artifact /srv/meili_data_new`
   — verifies the artifact, copies the snapshot into the new, empty data directory and makes the
   tables live. The Meilisearch version is read from the running instance (or give
   `--meilisearch-version`).
6. Restart Meilisearch (the same version) on the new data directory with
   `--import-snapshot /meili_data/geocoder.snapshot` (the path the command prints, as the instance
   sees it), e.g.
   `docker run -v /srv/meili_data_new:/meili_data getmeili/meilisearch:v1.35.1 meilisearch --import-snapshot /meili_data/geocoder.snapshot`.
7. `php bin/console tito10047-geo:geocoder:status`, then delete the snapshot file and the old data
   directory.

See [Deployment modes](docs/index.md#deployment-modes) for the details.

## Documentation

Read the [documentation](docs/index.md) to learn how to use the bundle: the full configuration
reference, the services, the commands, the Symfony UX Autocomplete integration and the
Photon-compatible API.

## OpenStreetMap attribution

The data comes from [OpenStreetMap](https://www.openstreetmap.org/) and is available under the
[Open Database License](https://www.openstreetmap.org/copyright). The bundle distributes no data,
but an application displaying addresses or routes must credit the contributors:
"© OpenStreetMap contributors".

## Development

The bundle is developed in the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder)
monorepo, together with the libraries: install the dependencies and run the checks from its root
(see its README).

The changes of each version are listed in the [changelog](CHANGELOG.md).

## License

Tito10047GeoBundle is released under the MIT License, see the [LICENSE](LICENSE) file.
