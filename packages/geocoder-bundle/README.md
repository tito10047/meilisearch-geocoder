# Tito10047GeocoderBundle

Self-hosted geocoder and address autocomplete for Symfony, built from OpenStreetMap data on Meilisearch

[![CI](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml/badge.svg)](https://github.com/tito10047/meilisearch-geocoder/actions/workflows/ci.yml)

Read-only split of the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder) monorepo: report issues and send pull requests there. The geocoder itself, usable without Symfony, is [tito10047/geocoder](https://github.com/tito10047/geocoder).

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
composer require tito10047/geocoder-bundle
```

### Applications that don't use Symfony Flex

#### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
composer require tito10047/geocoder-bundle
```

#### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project:

```php
// config/bundles.php

return [
    // ...
    Tito10047\GeocoderBundle\Tito10047GeocoderBundle::class => ['all' => true],
];
```

### Import the Routes

Import the routes of the bundle in your application, under the prefix of
your choice:

```yaml
# config/routes/tito10047_geocoder.yaml
tito10047_geocoder:
    resource: '@Tito10047GeocoderBundle/config/routes.php'
    prefix: /tito10047-geocoder
```

## Configuration

The bundle works without configuration. To change its defaults, configure the
`tito10047_geocoder` extension in your application:

```yaml
# config/packages/tito10047_geocoder.yaml
tito10047_geocoder:
    greeting: 'Hi'
```

Or in PHP:

```php
// config/packages/tito10047_geocoder.php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'tito10047_geocoder' => [
        'greeting' => 'Hi',
    ],
]);
```

Run `php bin/console config:dump-reference tito10047_geocoder` to list every option
and its default value, or read the [configuration reference](docs/index.md#configuration).

## Documentation

Read the [documentation](docs/index.md) to learn how to use the bundle.

## Development

The bundle is developed in the [tito10047/meilisearch-geocoder](https://github.com/tito10047/meilisearch-geocoder)
monorepo, together with the geocoder: install the dependencies and run the checks from its root
(see its README).

The changes of each version are listed in the [changelog](CHANGELOG.md).

## License

Tito10047GeocoderBundle is released under the MIT License, see the [LICENSE](LICENSE) file.
