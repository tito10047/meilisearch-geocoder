# Tito10047GeoBundle

Self-hosted geocoding, address autocomplete and routing for Symfony: OpenStreetMap data on Meilisearch and Valhalla

> Work in progress: the bundle is not usable yet.

[![CI](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml/badge.svg)](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml)

Read-only split of the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder) monorepo: report issues and send pull requests there. The libraries it configures, usable without Symfony, are [tito10047/geocoder](https://github.com/tito10047/geocoder) and [tito10047/routing](https://github.com/tito10047/routing).

## Requirements

- PHP 8.2 or higher (8.4 or higher with Symfony 8)
- Symfony 7.4 LTS or 8.1 and higher

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

## Documentation

Read the [documentation](docs/index.md) to learn how to use the bundle.

## Development

The bundle is developed in the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder)
monorepo, together with the libraries: install the dependencies and run the checks from its root
(see its README).

The changes of each version are listed in the [changelog](CHANGELOG.md).

## License

Tito10047GeoBundle is released under the MIT License, see the [LICENSE](LICENSE) file.
