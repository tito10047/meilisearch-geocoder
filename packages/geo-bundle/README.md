# Tito10047GeoBundle

Self-hosted geocoding, address autocomplete and routing for Symfony: OpenStreetMap data on Meilisearch and Valhalla

> Work in progress (0.1): searching addresses, routes, the commands and the UX Autocomplete
> integration work; the import of the data comes with the next version.

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

## Documentation

Read the [documentation](docs/index.md) to learn how to use the bundle: the full configuration
reference, the services, the commands and the Symfony UX Autocomplete integration.

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
