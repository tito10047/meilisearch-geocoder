# CLAUDE.md

Open-source (MIT) self-hosted geocoder / address autocomplete built from OpenStreetMap data
(Photon JSON dumps) on **Meilisearch**, with house numbers in an SQL table via PDO. Born from the
Tankovač project (`~/phpProjects/sukromne/tankovac`). Monorepo of two packages:

- `packages/geocoder/` — `tito10047/geocoder`, `Tito10047\Geocoder\`: all the logic, **no Symfony
  dependency** (not even in `require`).
- `packages/geocoder-bundle/` — `tito10047/geocoder-bundle`, `Tito10047\GeocoderBundle\`: only
  configuration, services, thin commands and integrations over the core.

Dependencies are installed once at the root; the root `composer.json` (dev only, never published)
replaces both packages and must also list every dependency added to a package. `composer qa`
validates the packages and runs tests, PHPStan and CS-Fixer for both; `composer rector` the
refactorings. The bundle comes from `tito10047/symfony-bundle-skeleton`; the code is still its
example (`Greeter` in the core, `Hello*` in the bundle).

## Communication

- Always communicate with the user in **Slovak**.
- Write all code and code comments in **English**.

## Start here

Everything learned so far is in `_docs/` (Slovak) — read `_docs/00-prehlad.md` first:
measurements, the data format and conversion rules, the Meilisearch settings and ranking that
work (and those that did not), existing projects, a draft design with open questions, and the
Valhalla routing notes for Tankovač. `_docs/08-analyza-a-verejne-api.md` is the analysis and the
proposed public API (name and licence decided, the rest still to be confirmed with the user).

## Data

`_data/` (~39 GB, git-ignored) holds the downloaded Photon dumps and PBFs of 9 countries, the
converted documents, a ready Meilisearch v1.35.1 database with the tuned index `geo4`, the house
numbers SQLite and Valhalla tiles — see `_docs/07-data-a-skripty.md`. Do not download them again;
do not delete or overwrite them without asking. `_lab/scripts/` are the experiment's prototypes
(`convert.php` holds the final conversion rules).
