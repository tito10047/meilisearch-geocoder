# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.1] - 2026-10-23

### Fixed

- An empty `geo.routing.url` at runtime (an empty `VALHALLA_URL`) no longer makes every service
  injecting `RouterInterface`, `MatrixInterface` or the routing status provider fail to be created:
  they are created, and routing throws `RoutingUnavailableException` "Valhalla is not configured:
  geo.routing.url is empty."; `geo:route:status` reports it. A given but invalid URL still fails
  when the service is created.
- An empty `geo.geocoder.meilisearch.url` at runtime switches the geocoder off the same way:
  `suggest()`, `geocode()`, `findByReference()` and the index manager throw
  `GeocoderUnavailableException` "Meilisearch is not configured: geo.geocoder.meilisearch.url is
  empty."; `geo:search`, `geo:status`, `geo:import`, … fail with that message, the Photon API answers
  503.
- The directory of a file-based SQLite `dsn` (e.g. `var/geo/`) is created before connecting, so the
  first `geo:import` no longer fails with "unable to open database file".
- Documented the empty `api_key` (no `Authorization` header) and the above in "Switching the
  geocoder or routing off by environment".

### Changed

- `geo.geocoder`, `geo.geocoder.address_lookup`, `geo.geocoder.search_backend`,
  `geo.geocoder.index_manager`, `geo.routing.router`, `geo.routing.matrix` and
  `geo.routing.status_provider` are created by a factory and typed by their interface; the concrete
  services are `<id>.configured` (geocoder) and `<id>.valhalla` (routing). Decorating the ids keeps
  working.

## [0.2.0] - 2026-10-23

### Changed

- Shorter names: the configuration key is `geo` (was `tito10047_geo`), the commands are `geo:search`,
  `geo:import`, `geo:publish`, `geo:status`, `geo:cleanup`, `geo:download`, `geo:artifact`,
  `geo:artifact:install`, `geo:route`, `geo:matrix` and `geo:route:status` (were
  `tito10047-geo:geocoder:…` and `tito10047-geo:routing:…`), the services, routes and log channel
  are prefixed `geo`.

### Added

- The `AddressLookupInterface` service: an address by its official id.
- First version of Tito10047GeoBundle.
- Command `geo:download [region…] [--force]`: the Photon dumps of the given or
  configured regions into `import.dumps_dir`, again only when they changed.
- `geocoder.import` options `regions`, `download_url`, `download_http_client`,
  `street_excluded_highways` and `reference_keys`.
- Photon-compatible HTTP API (`geocoder.photon_api`, disabled by default): `GET /api` from
  `@Tito10047GeoBundle/config/routes.php` (a route loader adding no route while disabled), Photon's
  parameters and GeoJSON, 400/503 as `{"message": …}`, `Cache-Control: public, max-age`
  (`max_age`), the OpenStreetMap licence as a `Link` header; checked against
  `geocoder-php/photon-provider`.
- Configuration with the sections `geocoder` (Meilisearch, database, tables, import rules, search
  defaults, UX Autocomplete) and `routing` (Valhalla), each enabled by configuring it.
- Services of tito10047/geocoder and tito10047/routing, one per collaborator, aliased to their
  interfaces; a lazy PDO connection from a DSN; existing Meilisearch, PDO and PSR-18 client
  services may replace the built ones.
- Default HTTP clients: Meilisearch without gzip (avoids a ~40 ms delayed-ACK stall on answers of
  a few kilobytes), Valhalla over curl or amphp (it keeps connections open).
- Commands `geo:search`, `geo:status`,
  `geo:route` and `geo:route:status`.
- An autocompleter of symfony/ux-autocomplete 3.5+ (`value` = `lat,lon|label`).
- Services of the import (each private, aliased to its interface): `Importer`, `FileExporter`,
  the Photon and export converters behind `ExportAwareConverter`, `SystemClock`, the build storage
  and the SQL dialect (lazy), `ArtifactBuilder` (lazy) and `ArtifactInstaller` through an
  `ArtifactInstallerFactory` taking the Meilisearch version given, configured or of the running
  instance.
- Configuration `geocoder.meilisearch.snapshot_dir` (artifacts) and `geocoder.meilisearch.version`
  (installing artifacts).
- Commands `geo:import` (`--no-publish`, `--export` files only, exports imported
  like dumps, dumps of `import.dumps_dir` by default), `…:publish`, `…:cleanup`, `…:artifact` and
  `…:install-artifact` (prints the `--import-snapshot` restart); `…:status` prints the live build
  (data timestamps per country, counts, schema version).
- The name catalog is tagged `kernel.reset`: long-running workers forget the remembered translations
  between requests.
- The distance matrix of tito10047/routing: service `geo.routing.matrix` aliased to
  `MatrixInterface` (same HTTP client as the router), its encoder, parser and error translator,
  configuration `routing.max_matrix_location_pairs` (2500), and the command
  `geo:matrix --source=… --target=… [--costing] [--radius]` printing a table.
- The connection built from the `dsn` reports an unreachable database as
  `GeocoderUnavailableException` instead of a `PDOException`.

## [0.1.0] - 2026-10-23

First published version. Superseded the same day by 0.2.0, which renamed the configuration key and the commands of the bundle.

[Unreleased]: https://github.com/tito10047/geo-bundle/compare/v0.2.1...HEAD
[0.2.1]: https://github.com/tito10047/geo-bundle/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/tito10047/geo-bundle/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/tito10047/geo-bundle/releases/tag/v0.1.0
