# Analýza projektu a návrh verejného API

Verzia 2 (2026-10-23), prepísaná načisto po rozhodnutiach z toho istého dňa. Podklady:
merania v `01`, dáta v `02`, Meilisearch v `03`, Valhalla v `06`, kód Tankovača
(`src/Route/Geocoding/*`, `src/Route/Routing/*`). Kód v ukážkach je **cieľový tvar API**,
nie hotová implementácia; „verejné API“ tu znamená rozhranie našich PHP tried.

## 1. Cieľ

Zbaviť sa cudzích verejných služieb (GraphHopper, openrouteservice — denné kvóty, nekomerčné
licencie) **pri hľadaní adries aj pri trasách** a bežať na vlastnom serveri v rozpočte
**1,2 GB RAM** pre všetky geo služby:

| | Služba | Pamäť pri behu (9 krajín, zmerané) | Rýchlosť |
|---|---|---|---|
| Adresy, písanie s návrhmi | Meilisearch + SQL tabuľka (PDO) | ~50 MiB | medián ~3–5 ms |
| Trasy | Valhalla (C++, 1 vlákno) | peak ~250 MiB | 100–250 ms na trasu |
| Obe naraz pod záťažou | | **~450 MB, bez OOM** | |

Výsledok je open-source PHP knižnica (MIT) + Symfony bundle; prvý používateľ je Tankovač,
ktorý nimi nahradí oboch poskytovateľov.

## 2. Rozhodnutia

| Téma | Rozhodnutie |
|---|---|
| Rozsah | adresy **aj** trasy |
| Balíky | `tito10047/geocoder`, `tito10047/routing` (bez Symfony) + `tito10047/geo-bundle` |
| Repozitár | monorepo, zverejnenie balíkov cez **`git subtree split`** do read-only repozitárov |
| Licencia | MIT (dáta OSM ostávajú ODbL, knižnica ich nedistribuuje) |
| PHP | ≥ 8.2; Symfony 7.4 LTS a 8.1+ (podľa skeletonu) |
| Index adries | jeden index pre všetky krajiny, krajina je filter |
| Hľadanie podľa prekladov | variant D: `names` + `cities` (obec + preklady) hľadateľné, `names_i18n` na zobrazenie |
| Domáca krajina | rerank rovnakých mien s poistkou `maxRankGap` = 25 000 |
| Popisky v jazyku | preklady obcí, krajov a krajín v SQL tabuľke `geo_name` |
| Prenos indexu | snapshot Meilisearchu pre samostatnú inštanciu; indexovanie na mieste pre zdieľanú |
| Práca | paralelne viacerí agenti, každý na vlastnej vetve (kap. 10) |

## 3. Balíky a repozitár

```
packages/geocoder/     tito10047/geocoder    Tito10047\Geocoder\     adresy: import, index, hľadanie
packages/routing/      tito10047/routing     Tito10047\Routing\      trasy: klient Valhally
packages/geo-bundle/   tito10047/geo-bundle  Tito10047\GeoBundle\    Symfony: konfigurácia, služby, príkazy, integrácie
composer.json, phpunit/phpstan/rector/cs-fixer, .github/           koreň: len vývoj, nikdy sa nezverejní
```

- **Jadrá bez Symfony**, ani v `require`. Bundle len skladá služby, konfiguráciu, tenké príkazy
  a integrácie; žiadna logika navyše.
- Bundle: trieda `Tito10047GeoBundle`, alias a prefix služieb `tito10047_geo`, príkazy
  `tito10047-geo:…`. Časti `geocoder:` a `routing:` sa zapínajú v konfigurácii zvlášť —
  kto chce len adresy, routing nemusí nastavovať.
- **Koreňový `composer.json`**: `replace` všetkých troch balíkov, autoload všetkých, všetky
  závislosti a skripty. Závislosť pridaná do balíka sa musí pridať aj sem.
- **Zverejnenie**: `bin/split` spraví pre každý balík `git subtree split --prefix=packages/<x>`
  do vetvy `split/<x>` a pushne ju do repozitára balíka (`tito10047/geocoder`,
  `tito10047/routing`, `tito10047/geo-bundle`) aj s tagom. Push je ručný krok autora, nie CI.
  Monorepo na GitHube pracovne `tito10047/meilisearch-geocoder`.
- Spoločné QA v koreni: `composer qa` = validácia `composer.json` balíkov, PHPUnit (sada na
  balík), PHPStan level 8, CS-Fixer; `composer rector` zvlášť. Jedno CI.

## 4. Rozsah po verziách

| Verzia | Adresy (`geocoder`) | Trasy (`routing`) |
|---|---|---|
| **v0.1** (potreba Tankovača) | import Photon výpisu, stavba indexu a výmena bez výpadku, `suggest()` s číslom domu, popiskom v jazyku a domácou krajinou, prenos snapshotom | `route()`: viac bodov, alternatívy, geometria, krajiny, mýto po krajinách, preklad chýb; skripty na stavbu a beh dlaždíc |
| v0.2 | sťahovanie výpisov (ETag) ✓, `geocode()`, HTTP API ako Photon, orientačné čísla (`606/42` podľa `42`) ✓, ulice bez zastávok a iných nie-ciest ✓, časť obce a okres ✓, oficiálne id adresy + `findByReference()` ✓ (schéma 2) | vyhýbanie sa mýtu a diaľniciam, matica vzdialeností (`sources_to_targets`) |
| neskôr | provider pre `geocoder-php`, `reverse()`, `attributeRank` v novších Meilisearchoch | izochróny, mapovanie GPS stopy |
| mimo | parsovanie PBF v PHP, distribúcia dát | dlaždice na zobrazenie mapy, navigácia krok za krokom |

## 5. Adresy — `tito10047/geocoder`

### 5.1 Ako to funguje

```
 import (silný stroj, alebo po krajinách na mieste)
 Photon výpis ─► DumpReader ─► Converter ─┬─► DocumentWriter (NDJSON / Meilisearch) ─► IndexManager ─► index geo_<čas> ─► compact ─► swap → "geo"
 (.jsonl.zst)    (stream)     (pravidlá) ├─► HouseNumberWriter (PDO, nová tabuľka) ───────────────────────────────────────► rename
                                           └─► NameWriter (preklady obcí, krajov, krajín → geo_name)

 hľadanie (produkcia)
 "Hlavná 12 Koš" ─► QueryParser ─► Meilisearch (q="Hlavná Koš") ─► čísla domov (street_key, "12") ─► Reranker ─► LabelFormatter ─► list<Suggestion>
```

Čo z meraní vyplýva pre návrh:

- **Stavba je drahá, hľadanie lacné**: indexovanie 9 krajín ~2 GB (pri 1 GB OOM), hľadanie
  ~50 MiB. Import preto stavia **nový** index a novú tabuľku a živé dáta vymení až na konci.
- **Pamäť PHP pri konverzii** nesmie rásť s počtom ulíc (prototyp DE 1,3 GB): dedup obcí
  a ulíc cez dočasnú SQLite tabuľku, nie cez pole.
- **Nastavenia pred dokumentmi** (zmena hľadateľných polí = preindexovanie); po stavbe
  **kompakcia** (−20 %, 5–8 s).
- **Jeden index**: hľadanie cez viac indexov porovnáva skóre, ktoré o `rank` nevie (37 → 31/43).

### 5.2 Dáta

Dokument v Meilisearchi (variant D, 4,42 mil. dokumentov, 2,78 GiB po kompakcii):

```json
{"id":"s791028875740832172","kind":"street","type":"street","name":"Stephansplatz",
 "names":["Stephansplatz"],"cities":["Wien","Viedeň","Vienna","Bécs","Vídeň","Wiedeń","Beč","Dunaj"],
 "city":"Wien","region":"","cc":"AT","postcode":null,"_geo":{"lat":48.2092503,"lng":16.3726684},"rank":25000}
```

| Pole | Hľadá sa | Obsah |
|---|---|---|
| `names` | áno | miestne meno + preklady (9 jazykov) — „viedeň“ nájde Wien |
| `cities` | áno | obec + jej preklady — „Stephansplatz Viedeň“ nájde ulicu vo Wien (17/38 → 36/38) |
| `names_i18n` | nie | `{"sk":"Viedeň",…}` len odlišné preklady, na meno výsledku v jazyku používateľa |
| `kind`, `cc` | filter | `place` / `street` / `poi`; krajina ISO alpha-2 |
| `rank` | pravidlo `rank:desc` | typ miesta × 1000 + `importance` × 999 (Roma 90 767) |
| `city`, `region`, `postcode`, `_geo`, `type`, `id` | nie | zobrazenie |

Pravidlá výberu (obce, POI len ako ciele ciest, ulice) a dedup sú v `02` a v
`_lab/scripts/convert.php`; v knižnici budú konfigurovateľné (`SelectionRules`).

SQL tabuľky (PDO; SQLite predvolene, MariaDB/PostgreSQL voliteľne):

```sql
CREATE TABLE geo_house (street_key BIGINT NOT NULL, number TEXT NOT NULL, orientation TEXT NULL,
                        postcode TEXT NULL, lat REAL NOT NULL, lon REAL NOT NULL);   -- 44 mil. riadkov, ~2,9 GB
CREATE INDEX geo_house_number ON geo_house (street_key, number);
CREATE INDEX geo_house_orientation ON geo_house (street_key, orientation);          -- "606/42" → orientation "42"

CREATE TABLE geo_name (cc TEXT NOT NULL, kind TEXT NOT NULL, name TEXT NOT NULL,    -- kind: city | region | country
                       lang TEXT NOT NULL, translation TEXT NOT NULL);              -- ~45 600 riadkov
CREATE TABLE geo_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL);                  -- data_timestamp krajín, verzia schémy
```

`street_key` = prvých 15 hex znakov `xxh64("CC|obec|ulica")` (60 bitov, zmestí sa do `BIGINT`);
ulica má v indexe `id` = `s<street_key>`.

### 5.3 Hľadanie — verejné API

```php
namespace Tito10047\Geocoder\Search;

interface GeocoderInterface
{
    /**
     * Suggestions while the user types (a possibly unfinished address or place name).
     *
     * @return list<Suggestion> best match first; empty when nothing matches
     *
     * @throws GeocoderUnavailableException when Meilisearch or the database cannot answer
     */
    public function suggest(string $text, ?SearchOptions $options = null): array;

    /** The single best match of a complete address, or null. (v0.2) */
    public function geocode(string $text, ?SearchOptions $options = null): ?Suggestion;
}

final readonly class SearchOptions
{
    public function __construct(
        public ?string $locale = null,            // labels in this language ("sk": "Viedeň"); null = local names
        public int $limit = 5,                    // 1..20
        public ?string $preferredCountry = null,  // "SK": wins among results of the same name, excludes nothing
        public array $countries = [],             // list<string> ISO alpha-2 filter; [] = all imported
        public array $kinds = [],                 // list<Kind> filter; [] = all
    ) {}
    // withLocale(), withLimit(), withPreferredCountry(), withCountries(), withKinds()
}

final readonly class Suggestion
{
    public function __construct(
        public string $id,              // "R109166" (OSM object), "s<street_key>" (street), "s<street_key>#12" (address)
        public Kind $kind,
        public string $type,            // OSM value: city, village, hotel, station, street, house
        public string $name,            // in the requested language when known: "Viedeň"
        public string $label,           // the whole line: "Stephansplatz, Viedeň, Rakúsko"
        public Coordinates $coordinates,
        public string $countryCode,     // "AT"
        public ?string $city = null,
        public ?string $region = null,
        public ?string $postcode = null,
        public ?string $street = null,  // Kind::Address
        public ?string $houseNumber = null,
    ) {}
}

enum Kind: string { case Place = 'place'; case Street = 'street'; case Address = 'address'; case Poi = 'poi'; }

namespace Tito10047\Geocoder;

final readonly class Coordinates { public function __construct(public float $latitude, public float $longitude) {} }
```

Čo `suggest()` robí:

1. Normalizuje medzery; pod 2 znaky vráti `[]` bez dotazu.
2. `QueryParser` vyberie token čísla domu (`^\d+[a-zA-Z]?(/\d+[a-zA-Z]?)?$`); PSČ „094 34“
   (dve skupiny čísel za sebou) ostane v texte.
3. Meilisearch: `q` = zvyšok, filter krajín a druhov, `limit` = 15 pri preferovanej krajine
   (kvôli reranku), inak `limit`.
4. Pri čísle domu dohľadá pre nájdené ulice čísla v `geo_house`; adresy idú pred svoju ulicu.
5. **Rerank domácej krajiny** (overené `_lab/scripts/quality-rerank.py`): medzi výsledkami
   s rovnakým menom a druhom (bez diakritiky a veľkosti písmen) ide preferovaná krajina
   dopredu, **ak cudzí víťaz nemá `rank` vyšší o viac ako `maxRankGap` = 25 000**. Domáca
   dedina tak predbehne cudzie mestečko (Bystré SK pred Bystré CZ), nikdy nie veľkomesto
   (bez poistky by „praha“ pre SK vrátila dedinu Praha). Výsledok: SK 43/43, CZ/HU/AT 42/43
   bez zhoršenia.
6. `LabelFormatter` poskladá `label` v jazyku z `names_i18n` a `geo_name`.

Výnimky: `Tito10047\Geocoder\Exception\ExceptionInterface`, `InvalidArgumentException`,
`GeocoderUnavailableException`.

Body rozšírenia (aplikácia ich môže nahradiť):

```php
interface LabelFormatterInterface   { public function format(Suggestion $suggestion, ?string $locale): string; }
interface RerankerInterface         { /** @param list<Suggestion> $s @return list<Suggestion> */ public function rerank(array $s, SearchOptions $options): array; }
interface HouseNumberStoreInterface { /** @return list<HouseNumber> */ public function find(int $streetKey, string $number, int $limit): array; }
```

### 5.4 Import, index a prenos — verejné API

```php
namespace Tito10047\Geocoder\Import;

final class Importer
{
    /**
     * Reads Photon dumps into a NEW index and NEW tables; nothing live changes until publish().
     *
     * @param iterable<string> $dumps paths or "php://stdin"; .zst through ext-zstd or `zstd -dc`
     */
    public function import(iterable $dumps, ?ImportProgressInterface $progress = null): Build;

    /** Swaps the built index in (Meilisearch /swap-indexes), renames the tables, drops the old ones. */
    public function publish(Build $build): void;
}

final readonly class Build        // index uid, table names, counts per country, data_timestamp of each dump
{ public string $indexUid; public string $houseTable; public string $nameTable; public array $counts; public array $dataTimestamps; }

interface ImportProgressInterface { public function advance(string $country, int $places, int $streets, int $pois, int $houses): void; }

/** What becomes a document; the defaults are the rules of convert.php. */
final readonly class SelectionRules
{
    public function __construct(
        public array $placeTypes = ['city' => 90, 'town' => 70, 'village' => 50, 'suburb' => 45,
                                    'borough' => 45, 'quarter' => 40, 'neighbourhood' => 30, 'hamlet' => 30],
        public array $poi = [/* osm_key => list of osm_value: tourism => [hotel, …], aeroway => [aerodrome], … */],
        public int $poiRank = 20,
        public int $streetRank = 25,
        public array $languages = ['sk', 'en', 'de', 'hu', 'cs', 'pl', 'hr', 'sl', 'it'],
        public float $placeDedupKm = 5.0,
    ) {}
}
```

Nižšia úroveň (verejná, napr. na stavbu bez Meilisearchu na tom istom stroji):
`PhotonDumpReader`, `Converter`, `NdjsonDocumentWriter`, `MeilisearchDocumentWriter`,
`PdoHouseNumberWriter`, `PdoNameWriter`, `Index\IndexManager` (`create`, `applySettings`,
`push`, `waitForTasks`, `compact`, `swap`, `delete`, `status`, `snapshot`).

**Prenos hotového indexu** (overené: snapshot 937 MB za 145 s; načítanie v kontajneri
s limitom 300 MB za 20 s pri 53 MiB):

```php
namespace Tito10047\Geocoder\Artifact;

final class ArtifactBuilder   { public function build(Build $build, string $dir): Artifact; }   // snapshot + SQLite + manifest.json
final class ArtifactInstaller { public function install(string $artifact, string $meiliDataDir, \PDO $database): void; }
```

`manifest.json`: verzia Meilisearchu, hash nastavení indexu, `data_timestamp` krajín, počty.
Snapshot sa načíta len pri štarte **prázdnej** inštancie **tej istej verzie** ⇒ dva režimy
nasadenia:

| Režim | Kedy | Ako |
|---|---|---|
| **Samostatná inštancia + artefakt** (odporúčané) | veľké regióny, malý server | stavba na silnom stroji, na serveri nový adresár dát + reštart kontajnera |
| Zdieľaná inštancia + indexovanie na mieste | 1–3 malé krajiny, Meilisearch iných projektov | `import` + `publish` priamo na serveri |

## 6. Trasy — `tito10047/routing`

### 6.1 Čo robí Valhalla a čo náš balík

- **Valhalla** (C++) robí výpočet: dlaždice z PBF postavia jej binárky
  (`valhalla_build_admins`, `valhalla_build_tiles`, 9 krajín 34 min, `tiles.tar` 13 GB), trasy
  počíta `valhalla_service`. To ostáva v Dockeri, nie v PHP.
- **Náš balík** robí všetko, čo je okolo, a čo sme sa naučili z meraní (`06`):
  - **klient** `/route` cez PSR-18 (viac bodov, alternatívy medzi 2 bodmi, `radius` na bod),
  - **geometriu** (dekódovanie polyline6),
  - **krajiny** na trase z `admin_crossings` (poradie, metre v každej),
  - **mýto po krajinách** z manévrov s `toll` prieniku so zmenami krajín podľa indexov
    bodov tvaru (presne na bod tvaru, bez druhej požiadavky),
  - **preklad chýb**: „No path could be found“ (442) → trasa neexistuje; „No suitable edges
    near location“ (171), sieť, 5xx → služba nedostupná (prevezme záloha),
  - **predvolený `radius: 200`**: inak bod v pešej zóne (Stephansplatz) nemá trasu,
  - **nastavenia, ktoré sa osvedčili** (`resources/docker/`: `compose.yaml`, stavba
    a spustenie dlaždíc): 1 vlákno, cache 20 MB, bez LRU a globálnej cache (tá padá
    na súbežných požiadavkách), `MALLOC_ARENA_MAX=2`.

### 6.2 Verejné API

```php
namespace Tito10047\Routing;

interface RouterInterface
{
    /**
     * @return non-empty-list<Route> the best route first
     *
     * @throws RouteNotFoundException     when no driving route goes through the points
     * @throws RoutingUnavailableException when Valhalla cannot answer now (or the points are outside its data)
     */
    public function route(RouteRequest $request): array;
}

final readonly class RouteRequest
{
    /** @param list<Coordinates> $points start, stops, destination — at least two */
    public function __construct(
        public array $points,
        public int $alternatives = 1,          // honoured between two points only (as every engine)
        public Costing $costing = Costing::Car,
        public int $radiusMeters = 200,        // how far a point may snap to a road
        public bool $avoidTolls = false,       // v0.2
        public bool $avoidHighways = false,    // v0.2
    ) {}
}

enum Costing: string { case Car = 'auto'; case Truck = 'truck'; case Bicycle = 'bicycle'; case Pedestrian = 'pedestrian'; }

final readonly class Route
{
    /**
     * @param list<Coordinates>    $geometry  the whole line, as Valhalla returns it
     * @param list<CountrySegment> $countries in the order driven, a country entered twice listed twice
     * @param list<CountrySegment> $tolled    metres on tolled roads, one per country, in the order first entered
     */
    public function __construct(
        public int $distanceMeters,
        public int $durationSeconds,
        public array $geometry,
        public array $countries,
        public array $tolled,
    ) {}

    /** @return array<string, int> metres per country */
    public function distanceByCountry(): array;

    /** @return array<string, int> tolled metres per country */
    public function tolledByCountry(): array;
}

final readonly class CountrySegment { public function __construct(public string $country, public int $distanceMeters) {} }
```

`Coordinates` je vlastná trieda balíka `routing` (balíky na sebe nezávisia; bundle ani
aplikácia ich medzi sebou nemusí prevádzať, majú rovnaký tvar). Výnimky:
`Tito10047\Routing\Exception\ExceptionInterface`, `InvalidArgumentException`,
`RouteNotFoundException`, `RoutingUnavailableException`.

Tvar zodpovedá `App\Route\Route` Tankovača (vzdialenosť, čas, geometria, krajiny, mýto),
takže jeho `ValhallaRouter` je len prevod. Riedenie geometrie a obce na trase ostávajú
v aplikácii.

### 6.3 Testy

Odpovede Valhally nahraté z lokálnej inštancie (`_data/valhalla/sk`, `all`) ako fixtures:
BA → Košice s alternatívami, BA → Split cez 4 krajiny (mýto SK 15 / HU 163 / SI 16 /
HR 477 km), Stephansplatz bez a s `radius`, bod mimo dát. Integračný test proti živej
Valhalle sa spustí, keď je nastavená `VALHALLA_URL`.

## 7. Symfony — `tito10047/geo-bundle`

### 7.1 Konfigurácia

```yaml
# config/packages/tito10047_geo.yaml
tito10047_geo:
    geocoder:
        enabled: true
        meilisearch:
            client: 'meilisearch.client'    # id of an existing Meilisearch\Client service
            # or: url: '%env(MEILISEARCH_URL)%', api_key: '%env(MEILISEARCH_API_KEY)%'
            index: 'geo'
        database:
            dsn: 'sqlite:%kernel.project_dir%/var/geo/geo.sqlite'   # or: connection: <PDO service id>
        import:
            dumps_dir: '%kernel.project_dir%/var/geo/dumps'
            regions: ['europe/slovakia', 'europe/czech-republic', 'europe/austria']
            languages: ['sk', 'en', 'cs', 'pl']
            poi: ~                          # null = defaults; osm_key => list of osm_value
        search:
            default_limit: 5
            preferred_country: ~            # e.g. 'SK'
            max_rank_gap: 25000
        ux_autocomplete: { enabled: auto }  # when symfony/ux-autocomplete is installed
        photon_api: { enabled: false }      # v0.2: GET /api?q=&lang=&limit=
    routing:
        enabled: true
        url: '%env(VALHALLA_URL)%'          # e.g. http://127.0.0.1:8002
        timeout: 10
        radius: 200
```

Služby: explicitné, privátne, s aliasmi na rozhrania jadier (`GeocoderInterface`,
`RouterInterface`, `Importer`, `IndexManager`, …); HTTP klient pre Valhallu cez
`symfony/http-client` (PSR-18).

### 7.2 Príkazy

| Príkaz | Čo |
|---|---|
| `tito10047-geo:geocoder:import [dump…] [--no-publish] [--ndjson=dir]` | nový index + tabuľky; `--ndjson` len súbory (stavba inde) |
| `tito10047-geo:geocoder:publish <build>` | výmena indexu a tabuliek |
| `tito10047-geo:geocoder:artifact <build> <dir>` / `…:install-artifact <file>` | prenos snapshotom |
| `tito10047-geo:geocoder:status` | aktívny index, počty, dátum dát |
| `tito10047-geo:geocoder:search "Hlavná 12 Košice" [--locale] [--country]` | ladenie |
| `tito10047-geo:geocoder:quality [file]` | regresný test rankingu (43 + 38 dotazov) |
| `tito10047-geo:geocoder:download [region…]` | v0.2: stiahne výpisy, preskočí nezmenené |
| `tito10047-geo:routing:status` | dostupnosť Valhally, verzia, pokrytie |
| `tito10047-geo:routing:route "48.14,17.10" "48.72,21.26" [--alternatives]` | ladenie: vzdialenosť, čas, krajiny, mýto |

### 7.3 Integrácie

- **UX Autocomplete**: autocompleter `value` = `lat,lon|label` (formát `PlaceCode`
  Tankovača), `text` = `label`, `?locale=` a `?country=` z dotazu; rate limiting je na aplikácii.
- **Photon API** (v0.2): GeoJSON v tvare Photonu → funguje `geocoder-php/photon-provider`.

### 7.4 Tankovač po integrácii

```php
#[AsTaggedItem(index: 'self-hosted', priority: 200)]
final class SelfHostedGeocoder implements GeocoderInterface        // App\Route\Geocoding\GeocoderInterface
{
    public function __construct(private readonly \Tito10047\Geocoder\Search\GeocoderInterface $geocoder) {}

    public function suggest(string $text, int $limit, string $locale): array
    {
        try {
            $found = $this->geocoder->suggest($text, new SearchOptions(
                locale: $locale, limit: $limit, preferredCountry: Locale::of($locale)->homeCountry()));
        } catch (GeocoderUnavailableException $e) {
            throw new ProviderUnavailableException($e->getMessage(), previous: $e);
        }

        return array_map(static fn (Suggestion $s): Place => new Place($s->label,
            new GeoPoint($s->coordinates->latitude, $s->coordinates->longitude)), $found);
    }
}

#[AsTaggedItem(index: 'valhalla', priority: 200)]
final class ValhallaRouter implements RouteProviderInterface      // App\Route\Routing\RouteProviderInterface
{
    // RouteRequest from the GeoPoints, Route → App\Route\Route (+ Geometry::thin()),
    // RouteNotFoundException → its RouteNotFoundException, RoutingUnavailableException → ProviderUnavailableException
}
```

GraphHopper a openrouteservice môžu ostať ako záloha s nižšou prioritou, alebo sa odstránia
(rozhodnutie pri integrácii).

## 8. Závislosti

| Balík | `require` | Poznámka |
|---|---|---|
| `geocoder` | `php`, `ext-pdo`, `meilisearch/meilisearch-php`, `psr/log` | Meilisearch klient potrebuje PSR-18 implementáciu od aplikácie (`php-http/discovery`) |
| `routing` | `php`, `psr/http-client`, `psr/http-factory`, `php-http/discovery`, `psr/log` | žiadny konkrétny HTTP klient |
| `geo-bundle` | `geocoder`, `routing`, Symfony 7.4/8.1 (`config`, `console`, `dependency-injection`, `framework-bundle`, `http-kernel`, `routing`), `symfony/http-client`, `nyholm/psr7` | `symfony/ux-autocomplete` voliteľne |
| koreň (dev) | všetko vyššie + PHPUnit, PHPStan, CS-Fixer, Rector | CI: Meilisearch **v1.35.1** v Dockeri; Valhalla len lokálne (`VALHALLA_URL`) |

Voliteľne `ext-zstd` (inak rúra `zstd -dc`).

## 9. Riziká

- **Indexovanie na zdieľanom Meilisearchu** ~2 GB pri 9 krajinách → odporúčaný režim je
  samostatná inštancia a artefakt.
- Ranking je ladený na Meilisearch 1.35.1; novšie verzie overiť príkazom `…:quality` v CI.
- **Valhalla image** `valhalla-scripted:latest` nie je pripnutý na verziu; v `compose.yaml`
  pripnúť konkrétny tag a overiť ním fixtures.
- Mýto po manévroch je presné na bod tvaru; proti GraphHopperu zatiaľ neoverené.
- Stavba dlaždíc 9 krajín: 34 min, 13 GB; na malom serveri nie — stavia sa inde a prenesie
  sa `tiles.tar` (prenositeľný súbor, Valhalla ho číta cez mmap).
- ODbL: knižnica dáta nedistribuuje; atribúcia „© OpenStreetMap contributors“ v README,
  v `…:status` a v Photon API.

## 10. Postup a rozdelenie práce

**Krok 0 — spoločný základ (jeden agent, pred ostatnými)**, aby si paralelná práca
neprepisovala spoločné súbory:

- `git init`, prvý commit; premenovanie `geocoder-bundle` → `geo-bundle`; kostra
  `packages/routing`; `bin/split` (git subtree);
- **kontrakty** z kap. 5.3, 5.4 a 6.2 ako kód (rozhrania, DTO, výnimky) bez implementácie;
- všetky závislosti z kap. 8 v koreni aj v balíkoch; fixture: vzorka Photon výpisu SK
  (pár stoviek riadkov, s atribúciou ODbL); Meilisearch 1.35.1 v CI.

**Potom paralelne, každý agent na vlastnej vetve (git worktree), len vo svojich adresároch:**

| Agent | Úloha | Hotové, keď |
|---|---|---|
| A — import | `PhotonDumpReader`, `Converter` (pravidlá `convert.php`, variant D, dedup cez dočasnú SQLite), writery NDJSON / čísel domov / `geo_name` | výstup pre vzorku = výstup prototypu; pamäť nerastie s počtom ulíc |
| B — index | `IndexManager`, `Importer` (`import`, `publish`), kompakcia, snapshot, `ArtifactBuilder` / `Installer` | stavba vzorky v Meilisearchi 1.35.1 v teste, výmena bez výpadku |
| C — hľadanie | `QueryParser`, `Geocoder::suggest()`, čísla domov, `Reranker`, `LabelFormatter` | 42/43 (s SK 43/43) a 36/38 na indexe `_data/exp-i18n/meili-d` |
| D — trasy | celý `tito10047/routing` + `resources/docker/` | fixtures z lokálnej Valhally: krajiny, mýto, chyby, `radius` |

**Nakoniec postupne:** E — bundle (konfigurácia, služby, príkazy, autocompleter) po zlúčení
A–D; F — napojenie Tankovača (iný repozitár; až po odsúhlasení).

Každú vetvu skontrolujem (`composer qa`, `composer rector`, prehľad kódu) a zlúčim do `main`.

## 11. Otvorené otázky

| Otázka | Návrh |
|---|---|
| Názvy repozitárov na GitHube | monorepo `tito10047/meilisearch-geocoder`, balíky `tito10047/geocoder`, `tito10047/routing`, `tito10047/geo-bundle` |
| Záloha v Tankovači | ponechať GraphHopper s nižšou prioritou, kým nové služby nebežia týždeň bez chyby |
| Prvé jazyky popiskov | 9 z prototypu (sk, en, de, hu, cs, pl, hr, sl, it) |
| Verzia Valhally | pripnúť tag image, ktorým postavíme fixtures |
