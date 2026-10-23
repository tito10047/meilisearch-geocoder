# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- First version of Tito10047GeoBundle.
- Configuration with the sections `geocoder` (Meilisearch, database, tables, import rules, search
  defaults, UX Autocomplete) and `routing` (Valhalla), each enabled by configuring it.
- Services of tito10047/geocoder and tito10047/routing, one per collaborator, aliased to their
  interfaces; a lazy PDO connection from a DSN; existing Meilisearch, PDO and PSR-18 client
  services may replace the built ones.
- Default HTTP clients: Meilisearch without gzip (avoids a ~40 ms delayed-ACK stall on answers of
  a few kilobytes), Valhalla over curl or amphp (it keeps connections open).
- Commands `tito10047-geo:geocoder:search`, `tito10047-geo:geocoder:status`,
  `tito10047-geo:routing:route` and `tito10047-geo:routing:status`.
- An autocompleter of symfony/ux-autocomplete 3.5+ (`value` = `lat,lon|label`).
