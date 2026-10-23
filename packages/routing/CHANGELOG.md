# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-10-23

### Added

- `MatrixInterface` with `MatrixRequest` (sources, targets, costing, snapping radius, avoiding
  tolls and motorways) and `Matrix` (distance and duration per pair, null for a pair without a
  route, `entries()` of `MatrixEntry`, `nearestTarget()`).
- `ValhallaMatrix` over Valhalla's `/sources_to_targets` (concise answer), refusing matrices
  larger than its `maxLocationPairs` (2,500 by default, as Valhalla); every error of the matrix
  is a `RoutingUnavailableException` (`ErrorTranslator::forMatrix()`: Valhalla answers 170 for a
  point outside the data).
- `ErrorTranslator` takes the error codes that mean "no route".

- `RouterInterface` with `RouteRequest` (points, alternatives, costing, snapping radius 200 m by
  default, avoiding tolls and motorways as a preference) and `Route` (distance, duration,
  geometry, countries in the order driven, tolled distance per country).
- `ValhallaRouter` over any PSR-18 client and PSR-17 factories (discovered when not given),
  with a PSR-3 logger.
- Countries from Valhalla's admin crossings and tolled distance per country from the tolled
  maneuvers cut at the borders.
- Polyline decoder (polyline6 for Valhalla, any precision).
- `RouteNotFoundException` (errors 170, 441, 442) and `RoutingUnavailableException` (any
  other error, network errors, timeouts, invalid answers).
- Docker resources: `compose.yaml` with a pinned Valhalla image and the settings for a small
  server, scripts to build the tiles and serve them.

## [0.1.0] - 2026-10-23

First published version, superseded the same day by 0.2.0.

[Unreleased]: https://github.com/tito10047/routing/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/tito10047/routing/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/tito10047/routing/releases/tag/v0.1.0
