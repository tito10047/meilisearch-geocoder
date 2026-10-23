# Meilisearch geocoder

Development monorepo of a self-hosted geocoder and address autocomplete built from
OpenStreetMap data on Meilisearch:

| Package | Directory | |
|---|---|---|
| [`tito10047/geocoder`](packages/geocoder) | `packages/geocoder/` | the geocoder, without a framework |
| [`tito10047/geocoder-bundle`](packages/geocoder-bundle) | `packages/geocoder-bundle/` | its Symfony bundle |

Each package is published from a read-only repository split from this one; this repository
itself is not installable.

## Development

Install the dependencies once, at the root, and run the checks of both packages:

```console
composer install
composer test
```

| Command | Description |
|---|---|
| `composer qa` | Runs every quality check |
| `composer test` | Runs the tests of both packages (PHPUnit; `--testsuite geocoder` or `geocoder-bundle` for one) |
| `composer phpstan` | Runs the static analysis (PHPStan) |
| `composer cs` | Checks the coding standards (PHP-CS-Fixer) |
| `composer cs:fix` | Fixes the coding standards (PHP-CS-Fixer) |
| `composer rector` | Lists the automated refactorings (Rector) |
| `composer rector:fix` | Applies the automated refactorings (Rector) |
| `composer validate:packages` | Validates the `composer.json` of every package |

The root `composer.json` replaces both packages and autoloads their code: a change to the
geocoder and the bundle is one commit. A dependency added to a package must also be added
to the root `composer.json`.

## License

Released under the MIT License, see the [LICENSE](LICENSE) file.
