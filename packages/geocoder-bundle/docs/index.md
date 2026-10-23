# Tito10047GeocoderBundle

Self-hosted geocoder and address autocomplete for Symfony, built from OpenStreetMap data on Meilisearch

## Installation

Follow the [installation instructions](../README.md#installation) of the README.

## Configuration

The bundle works without configuration. These are the options of the
`tito10047_geocoder` extension, with their default values:

```yaml
# config/packages/tito10047_geocoder.yaml
tito10047_geocoder:
    # The word people are greeted with, e.g. "Hello" in "Hello Jane!".
    greeting: 'Hello' # Example: 'Hi'
```

The same configuration in PHP:

```php
// config/packages/tito10047_geocoder.php
namespace Symfony\Component\DependencyInjection\Loader\Configurator;

return App::config([
    'tito10047_geocoder' => [
        'greeting' => 'Hello',
    ],
]);
```

Run `php bin/console config:dump-reference tito10047_geocoder` to display this
reference, and `php bin/console debug:config tito10047_geocoder` to display the
configuration of your application.

## Usage

The bundle provides a greeter service. Type-hint `Tito10047\Geocoder\GreeterInterface`
in the constructor of your services to get it injected by autowiring:

```php
namespace App\Service;

use Tito10047\Geocoder\GreeterInterface;

final class Welcome
{
    public function __construct(
        private readonly GreeterInterface $greeter,
    ) {
    }

    public function welcome(string $name): string
    {
        return $this->greeter->greet($name); // "Hello Jane!" for "Jane"
    }
}
```

Every exception thrown by the bundle implements `Tito10047\Geocoder\Exception\ExceptionInterface`.

## Routes

The bundle provides a page greeting people, once its routes are imported
(see the [installation instructions](../README.md#installation)):

| Route | Path | Controller |
|---|---|---|
| `tito10047_geocoder_hello` | `/hello/{name}` | `Tito10047\GeocoderBundle\Controller\HelloController` |

With the `/tito10047-geocoder` prefix, `/tito10047-geocoder/hello/Jane` greets "Hello Jane!", and
`/tito10047-geocoder/hello` greets "Hello World!".
The page is plain text.

Link to the page by the name of its route, never by its path, which depends
on the prefix chosen by the application:

```twig
<a href="{{ path('tito10047_geocoder_hello', {name: 'Jane'}) }}">Greet Jane</a>
```

Run `php bin/console debug:router tito10047_geocoder_hello` to display the route.

## Console Command

The `tito10047-geocoder:hello` command greets someone with the greeter:

```console
$ php bin/console tito10047-geocoder:hello Jane

 [OK] Hello Jane!

$ php bin/console tito10047-geocoder:hello Jane --yell

 [OK] HELLO JANE!
```

Run `php bin/console tito10047-geocoder:hello --help` to display its usage, and
`php bin/console list tito10047-geocoder` to list the commands of the bundle.
