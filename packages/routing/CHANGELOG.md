# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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
