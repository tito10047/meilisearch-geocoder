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
- `Status\GeocoderStatusProviderInterface` and `Status\GeocoderStatusProvider`: the state of the
  live index (`IndexStatus`, Meilisearch version) and of the live build (`Build`: data timestamps
  per country, counts) as a `Status\GeocoderStatus`, without throwing on an outage:
  `Status\GeocoderState` `Ready`, `NoIndex`, `Unreachable` or `NotConfigured` (an index manager
  throwing `GeocoderNotConfiguredException`); the build is read independently, with its own error.
- `IndexManagerInterface::fingerprint()` (what tells an index apart and moves with it on a swap:
  the creation time in Meilisearch) and `IndexManagerInterface::version()`.
- `Index\Exception\TaskNotFoundException`: Meilisearch does not return an enqueued task (deleted,
  or hidden from the API key).
- `IndexPublisher::swapIn()`, `swapBack()` and `removeReplaced()`, the steps of `publish()`.
- `BuildStorageInterface::recordPublication()` / `recordedPublication()`: the fingerprint of the
  build index, recorded in the metadata of its tables when its publication starts.
- `HttpDumpDownloader` sets the permissions of what it creates, `$fileMode` (0644) and
  `$directoryMode` (0755), whatever the umask.
- `Import\DumpSummary::nameOf()`: the portable name of a dump (its file name, a `php://` stream as
  it is).

### Changed

- `Importer::publish()` is resumable: it records the fingerprint of the build index, swaps only
  while the live index is not that one, undoes the swap of the run when the tables cannot be
  published, and deletes the replaced index only once the tables are live. Running it again with
  the same build — or with the live build once the tables are live — converges to a consistent
  state from wherever a former run stopped. `Importer::build()` also returns a build whose index was
  swapped in already (its publication stopped before the tables).
- `MeilisearchIndexManager::swap()` reads the creation times of both indexes first and fails with
  `MeilisearchException` when the build index does not exist (instead of a failed task).
- An unknown task is a `TaskNotFoundException` (was a `MeilisearchException`; both are
  `RuntimeException`s).
- **BC break for implementers** of `IndexManagerInterface` (`fingerprint()`, `version()`) and
  `BuildStorageInterface` (`recordPublication()`, `recordedPublication()`).
- **BC break:** `Import\ConverterInterface::convert()` returns a non-empty `list<DumpSummary>`
  instead of one `DumpSummary`: one per dump read, so that a converter of something holding several
  converted dumps can return each. `PhotonConverter` returns one; implementations must wrap theirs
  in an array.
- A build stores the file name of each dump (`DumpSummary::nameOf()`), never the path it had on
  the machine that converted it: `PhotonConverter` names its summary so, and `PdoBuildMetadata`
  writes so whatever a converter gives.

### Fixed

- Publishing with an API key restricted to the indexes of the geocoder (`"indexes":
  ["tankovac_geo*"]`) failed with "Meilisearch does not know the task N" after the swap had taken
  effect, leaving the new index live with the old tables: Meilisearch hides the tasks of no index
  (swaps, snapshots) from such a key. The swap is now confirmed from the indexes, polled with the
  task timeout; a snapshot followed by such a key fails with the key it needs (`"indexes": ["*"]`).
- Importing an export (`File\FileExportConverter`, files-only mode) no longer records the absolute
  path of the export directory as the one dump of the build with the oldest timestamp for every
  country: it returns the summaries of the original dumps the export carries in its metadata (the
  `dumps` of `geo_meta` in `geocoder.sqlite`: name, data timestamp, countries, counts), so the
  published build's metadata, per-country data timestamps and counts equal those of importing the
  dumps directly. Exports of 0.2 carry the same manifest under absolute paths: only the file names
  are kept. An export whose files do not hold the counts of its manifest is refused as damaged.

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
