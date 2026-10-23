# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Search\QueryMatch` (`matchedWords`, `queryWords`, `isComplete()`) and `Suggestion::$match`
  (with `withMatch()` and `isCompleteMatch()`): how many words of the searched text (without the
  house number; an address as its street) a suggestion matched, from Meilisearch's `words` ranking
  details. Null for `AddressLookup::findByReference()` (nothing searched) and when the engine does
  not tell.
- `SearchOptions::$completeMatchesOnly` / `withCompleteMatchesOnly()`: drops the partial matches
  after the reranking, so that a text outside the imported countries ("Champs-Élysées, Paris" in an
  index without France) gives `[]` and an application can fall back to another geocoder.
- `SearchHit::$queryWords` and `SearchHit::match()`; `MeilisearchSearchBackend` reads
  `maxMatchingWords`.
- `Exception\GeocoderNotConfiguredException`, a `GeocoderUnavailableException` for a geocoder
  switched off by the configuration rather than down.

## [0.2.0] - 2026-10-23

### Added

- First version of the geocoder.
- `Photon\*`: the search API of Photon over `GeocoderInterface`, framework-free —
  `PhotonQuery::fromQueryString()` (validation like Photon 1.3, repeated filters), `PhotonSearch`
  (layers, OSM tag and category filters, bounding box), `FeatureFactory` (a `Suggestion` as a
  GeoJSON `Feature` with Photon's properties) and `FeatureCollection`.
- `Import\File\ExportAwareConverter`: one Importer indexes the directories of exports and the
  dumps alike.
- `PdoNameCatalog::reset()` forgets the remembered translations (for long-running workers).
- `Import\Download`: `DumpDownloaderInterface` and `HttpDumpDownloader` (PSR-18) download the
  Photon dump of a region into a directory — streamed beside the previous copy, its header
  checked, renamed over it; `ETag`/`Last-Modified` kept in a sidecar file for a conditional GET
  that skips an unchanged dump; `DownloadProgressInterface`; `GraphHopperRegionUrlResolver`
  (`europe/slovakia` → GraphHopper's layout, configurable base URL). Requires `psr/http-factory`.
- `SelectionRules::$streetExcludedHighways` (and `isStreet()`): the named `highway` values that are
  no streets (bus stops, platforms, motorway junctions, rest areas, proposed roads, …); 22.9 % of
  the street documents of the Slovak dump were such.
- The parts of an address: `Document::$suburb` and `$county` (displayed fields of the index),
  `HouseNumber::$suburb` (a house's own part of the town), `Suggestion::$suburb` and `$county`
  (translated through the catalog: `NameKind::Suburb` and `NameKind::County`), mapped to the
  Photon properties `district` and `county`.
- Official address ids: `SelectionRules::$referenceKeys` (default `SK => ref:minvskaddress`),
  `HouseNumber::$reference`, `Suggestion::$reference`, `Search\AddressLookupInterface::findByReference()`
  (`AddressLookup`, `GeocoderFactory::createAddressLookup()`), backed by
  `HouseNumber\HouseNumberReferenceStoreInterface` (`PdoHouseNumberStore`) and
  `Search\Backend\DocumentSourceInterface` (`MeilisearchSearchBackend::document()`).

### Changed

- `PdoSchema::VERSION` 2: `geo_house` gains `suburb` and `reference` (indexed). Builds, exports
  and table copies of another schema version are refused; import the dumps again.
- `IndexSettings`: the displayed attributes gain `suburb` and `county` (a new settings hash).
- `AddressInterpreterInterface` gains `suburb()`, `county()`, `suburbTranslations()` and
  `countyTranslations()`; `Photon\Place` gains its `extra` tags.

## [0.1.0] - 2026-10-23

First published version, superseded the same day by 0.2.0.

[Unreleased]: https://github.com/tito10047/geocoder/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/tito10047/geocoder/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/tito10047/geocoder/releases/tag/v0.1.0
