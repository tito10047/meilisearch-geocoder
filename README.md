# Meilisearch geocoder

Development monorepo of a self-hosted geocoder and address autocomplete built from
OpenStreetMap data on Meilisearch, and driving routes from Valhalla:

| Package | Directory | |
|---|---|---|
| [`tito10047/geocoder`](packages/geocoder) | `packages/geocoder/` | the geocoder, without a framework |
| [`tito10047/routing`](packages/routing) | `packages/routing/` | driving routes from Valhalla, without a framework |
| [`tito10047/geo-bundle`](packages/geo-bundle) | `packages/geo-bundle/` | the Symfony bundle of both |

Each package is published from a read-only repository split from this one; this repository
itself is not installable.

## Development

Install the dependencies once, at the root, and run the checks of every package:

```console
composer install
composer test
```

| Command | Description |
|---|---|
| `composer qa` | Runs every quality check |
| `composer test` | Runs the tests of every package (PHPUnit; `--testsuite geocoder`, `routing` or `geo-bundle` for one) |
| `composer phpstan` | Runs the static analysis (PHPStan) |
| `composer cs` | Checks the coding standards (PHP-CS-Fixer) |
| `composer cs:fix` | Fixes the coding standards (PHP-CS-Fixer) |
| `composer rector` | Lists the automated refactorings (Rector) |
| `composer rector:fix` | Applies the automated refactorings (Rector) |
| `composer validate:packages` | Validates the `composer.json` of every package |

The root `composer.json` replaces every package and autoloads its code: a change to a
library and the bundle is one commit. A dependency added to a package must also be added
to the root `composer.json`.

## License

Released under the MIT License, see the [LICENSE](LICENSE) file.

## Publishing

Each package is published from its own read-only repository (`tito10047/geocoder`,
`tito10047/routing`, `tito10047/geo-bundle`), split from this one with `git subtree`
(Fedora: `dnf install git-subtree`):

```console
bin/split                          # split/<package> branches
bin/split --push --tag v0.1.0      # push them and the tag to the package repositories
```

Pushing is a manual step of the maintainer, never done by CI.
