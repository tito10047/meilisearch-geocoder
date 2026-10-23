# CLAUDE.md

Open-source (MIT) self-hosted geocoder / address autocomplete built from OpenStreetMap data
(Photon JSON dumps) on **Meilisearch**, with house numbers in an SQL table via PDO, and driving
routes from a self-hosted **Valhalla**. Born from the Tankovač project
(`~/phpProjects/sukromne/tankovac`). Monorepo of three packages:

- `packages/geocoder/` — `tito10047/geocoder`, `Tito10047\Geocoder\`: addresses (import, index,
  search), **no Symfony dependency** (not even in `require`).
- `packages/routing/` — `tito10047/routing`, `Tito10047\Routing\`: the Valhalla client, no Symfony
  either; the two libraries do not depend on each other.
- `packages/geo-bundle/` — `tito10047/geo-bundle`, `Tito10047\GeoBundle\`: only configuration,
  services, thin commands and integrations over both libraries (alias and service prefix
  `tito10047_geo`, commands `tito10047-geo:…`).

Dependencies are installed once at the root; the root `composer.json` (dev only, never published)
replaces every package and must also list every dependency added to a package. `composer qa`
validates the packages and runs tests, PHPStan and CS-Fixer for all of them; `composer rector` the
refactorings.

## Communication

- Always communicate with the user in **Slovak**.
- Write all code and code comments in **English**.

## Start here

Everything learned so far is in `_docs/` (Slovak) — read `_docs/00-prehlad.md` first:
measurements, the data format and conversion rules, the Meilisearch settings and ranking that
work (and those that did not), existing projects, and the Valhalla routing notes for Tankovač.
**`_docs/08-analyza-a-verejne-api.md` is the current design** (packages, public API, versions,
work split).

## Code rules

Professional library code: robust, extensible, no shortcuts.

- **Every class is `final`** (value objects `final readonly`). Extension points are **interfaces**:
  a collaborator another implementation could replace (a store, a writer, a formatter, a client)
  is typed by its interface and injected through the constructor. No inheritance for reuse, no
  `protected`, no traits, no static state, no service locators.
- The contracts in `packages/geocoder/src` (`Coordinates`, `StreetKey`, `Document\*`,
  `HouseNumber\*`, `Name\*`, `Storage\TableNames`, `Search\*`, `Import\*`) are shared by the
  parallel work: **do not change them on a feature branch**; if one is wrong, say so in the report.
- Value objects validate in their constructor and throw the package's
  `Exception\InvalidArgumentException`; runtime failures throw a subclass of
  `Exception\RuntimeException` that wraps the original exception as `previous`. Every exception
  implements the package's `Exception\ExceptionInterface`. Never leak a vendor exception
  (Meilisearch, PDO, PSR-18) through a public method.
- No `mixed` arrays crossing class boundaries: typed DTOs, `list<…>` / `array<k, v>` PHPDoc,
  enums instead of string constants.
- SQL only through prepared statements; identifiers only from `Storage\TableNames` (validated).
  Portable SQL (SQLite, MariaDB, PostgreSQL) unless a dialect is explicitly abstracted.
- Memory must not grow with the size of a dump: stream, use generators, buffer in batches.
- Code style is Symfony's (CS-Fixer `@Symfony`), no `declare(strict_types=1)`, PHP ≥ 8.2 (no
  property hooks or other 8.4+ syntax). PHPStan level 8 clean; Rector clean.
- Tests: PHPUnit, one test class per class, data providers for edge cases, fakes (small final
  classes implementing the interfaces in `tests/`) rather than mocks of our own classes.
  Integration tests needing a service (Meilisearch, Valhalla) are skipped unless their env
  variable is set (`MEILISEARCH_URL`, `VALHALLA_URL`).
- Comments explain why, not what; PHPDoc on every public method of an interface.

## Data

`_data/` (~39 GB, git-ignored) holds the downloaded Photon dumps and PBFs of 9 countries, the
converted documents, a ready Meilisearch v1.35.1 database with the tuned index `geo4`, the house
numbers SQLite and Valhalla tiles — see `_docs/07-data-a-skripty.md`. Do not download them again;
do not delete or overwrite them without asking (copy with `cp -a --reflink=auto` to experiment).
`_lab/scripts/` are the experiment's prototypes (`convert.php` / `convert-i18n.php` hold the final
conversion rules).
