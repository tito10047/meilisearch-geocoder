# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
