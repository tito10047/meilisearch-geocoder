# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] - 2026-10-23

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
- `Import\Workspace\TranslationCandidate` and `TranslationSource`: the votes for the translations
  of names, counted by the conversion workspace.

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
- **BC break** for implementations: `ConversionWorkspaceInterface` has `voteForTranslation()` and
  `translationCandidates()`; `AddressInterpreterInterface` has `catalogTranslations()`;
  `TranslationExtractorInterface` has `fromOwnNames()`, and its `fromPlace()` gives candidates,
  translations equal to the name included (see the fix of the town translations).
- A build stores the file name of each dump (`DumpSummary::nameOf()`), never the path it had on
  the machine that converted it: `PhotonConverter` names its summary so, and `PdoBuildMetadata`
  writes so whatever a converter gives.

### Fixed

- Publishing with an API key restricted to the indexes of the geocoder (`"indexes":
  ["tankovac_geo*"]`) failed with "Meilisearch does not know the task N" after the swap had taken
  effect, leaving the new index live with the old tables: Meilisearch hides the tasks of no index
  (swaps, snapshots) from such a key. The swap is now confirmed from the indexes, polled with the
  task timeout; a snapshot followed by such a key fails with the key it needs (`"indexes": ["*"]`).
- Wrong translations of towns in the catalog of names (`geo_name`) and in the labels: Photon takes
  the town of an address from the object's own `addr:city` but its translations (`city:xx`) from
  the boundary the object lies in, and the first translation written won. A building in Bytča
  tagged `addr:city=Bratislava` made Bratislava "Bytča" in Slovak; Praha was "Přezletice" in
  Czech, Zürich "Andelfingen" in German and "Bazilej" in Slovak, Wien "Groß-Enzersdorf" and Graz
  "Bad Loipersdorf" in German, Graz "Linz" in English. Now every address votes for the
  translations of its town, part of the town, county and region (the name itself, i.e. no
  translation, included), and once the dump is read each name gets, in each language, the
  translation most addresses gave; the votes are summed in the SQLite workspace (memory stays
  bounded). The addresses of a town or part of a town vote only when one of their translations,
  in any language, is its local name (`PhotonAddressInterpreter::catalogTranslations()`). Where no
  address translates a name in a language, the records of the towns and parts of towns of that
  name (`place=city|town|village|…`) vote with their own `name:xx`. A tie goes to no translation.
  Measured on the dumps of 2026-10-17 against 0.2.1 (town, part of a town; counties, regions and
  countries are unchanged except one Swiss district):

  | Country | Towns: kept / removed / changed / added | Parts of towns: kept / removed / changed / added |
  |---|---|---|
  | Slovakia | 3,098 / 699 / 51 / 0 | 3,136 / 573 / 66 / 243 |
  | Czechia | 3,416 / 2,826 / 167 / 1,238 | 3,533 / 180 / 129 / 777 |
  | Austria | 686 / 4,881 / 145 / 460 | 794 / 89 / 3 / 558 |
  | Switzerland, Liechtenstein | 145 / 1,804 / 7 / 16 | 51 / 1 / 0 / 44 |

  The removed ones are mostly translations of another town (a village translated as its
  municipality, "Praha 9" as Praha, Wabern as Bern) and of misspelt names; the added ones come from
  the records of the towns. The conversion takes about 20 % longer.
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

[Unreleased]: https://github.com/tito10047/geocoder/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/tito10047/geocoder/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/tito10047/geocoder/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/tito10047/geocoder/releases/tag/v0.1.0
