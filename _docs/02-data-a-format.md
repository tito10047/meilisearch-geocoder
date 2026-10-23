# Dáta a ich formát

## Zdroj: Photon JSON výpisy (GraphHopper)

- Po krajinách: `https://download1.graphhopper.com/public/europe/<krajina>/photon-dump-<krajina>-1.0-latest.jsonl.zst`
  (napr. `slovakia`, `czech-republic`, `austria`, `hungary`, `poland`, `croatia`, `slovenia`,
  `germany`, `italy`). Zoznam regiónov: `https://download1.graphhopper.com/public/europe/`.
- Celá planéta: `https://download1.graphhopper.com/public/photon-dump-planet-1.0-latest.jsonl.zst`
  (~27 GB, týždenne).
- Aktualizované **týždenne** (dátum v hlavičke, `data_timestamp`; naše súbory z 2026-10-17/19).
- Pozor: staršie **hotové DB po krajinách** (`/public/extracts/by-country-code/sk/photon-db-sk-*.tar.bz2`)
  sú z júla 2025 a pre Photon 0.x — nepoužívať. Výpisy `photon-dump-*-1.0-*` sú aktuálne.
- Formát: „Nominatim Dump File“ v0.1.0, špecifikácia CC0:
  https://github.com/komoot/photon/blob/master/docs/json-dump-format-0.1.0.md
- Dáta: **ODbL** (© OpenStreetMap contributors). Index postavený z nich je odvodená databáza →
  atribúcia povinná; share-alike sa týka databázy, nie kódu knižnice. Knižnica nech dáta
  nedistribuuje, len stiahne.
- Komprimované **zstd**: PHP buď PECL `ext-zstd`, alebo rúra `zstd -dc | php …` (tak to robí
  `convert.php`). Veľkosti: SK 173 MB, CZ 233 MB, AT 334 MB, HU 107 MB, PL 850 MB, HR 53 MB,
  SI 52 MB, DE 2,35 GB, IT ~1,1 GB.

Výhoda oproti PBF: adresná hierarchia (do ktorej obce, kraja, krajiny miesto patrí), mená
a `importance` už spočítal Nominatim. PHP parsery PBF (`franksierra/osm-pbf`) vedia len
surové uzly/cesty/relácie, bez geometrie a hierarchie.

## Riadky výpisu (JSON Lines)

1. Hlavička:
   ```json
   {"type":"NominatimDumpFile","content":{"version":"0.1.0","generator":"photon","database_version":"1.0.0-4","data_timestamp":"2026-10-17T23:02:06.000+00:00","features":{"sorted_by_country":true,"has_addresslines":false}}}
   ```
   `has_addresslines: false` → adresa je priamo v zázname (netreba riešiť odkazy).
2. `{"type":"CountryInfo","content":[{"country_code":"sk","name":{"name":"Slovensko","name:en":"Slovakia",…}},…]}`
   — mená všetkých krajín.
3. `{"type":"Place","content":[{…}]}` — jedno miesto (pole s jedným objektom). Polia:

| Pole | Príklad | Poznámka |
|---|---|---|
| `place_id` | `"173645320"` | Nominatim id |
| `object_type`, `object_id` | `"N"`/`"W"`/`"R"`, `1047534408` | OSM objekt; naše `id` = `N1047534408` |
| `osm_key`, `osm_value` | `place`/`city`, `highway`/`residential`, `tourism`/`hotel` | typ |
| `categories` | `["osm.waterway.drain"]` | |
| `address_type` | `other`, `locality`, … | |
| `importance` | `0.1067` | 0–1, z Wikipédie a pod.; použité na rozhodovanie remíz |
| `name` | `{"name":"Wien","name:sk":"Viedeň","name:cs":"Vídeň",…}` | chýba pri bezmenných (adresné body) |
| `housenumber` | `"606/42"` | len adresy |
| `address` | `{"street":"Družstevná","city":"Bystré","county":"okres …","state":"Prešovský kraj","suburb":…,"city:hu":…,"state:en":…}` | aj preložené kľúče `city:xx`, `state:xx` |
| `postcode` | `"094 34"` | |
| `country_code` | `"sk"` | malými |
| `centroid` | `[21.55, 49.01]` | **lon, lat** |
| `bbox` | `[minlon, minlat, maxlon, maxlat]` | |
| `extra` | `{"ref:minvskaddress":"7101722639",…}` | doplnkové tagy |

Obec býva vo výpise **dvakrát**: hranica (`R…`, často bez `city` v adrese, bez PSČ) a bod
(`N…`, s PSČ), niekedy s iným typom (Žilina: relácia `town`, uzol `city`) a súradnicami
vzdialenými až ~2 km (Košice). Treba deduplikovať.

## Čo je vo výpise (SK, `census.py`)

| | Počet |
|---|---|
| s `housenumber` | 1 633 630 |
| `place` s menom | 116 624 (z toho veľa `locality` — pomenované polia, `isolated_dwelling`) |
| `highway` s menom (úseky ulíc) | 117 994 → **41 976** po zlúčení na ulicu + obec |
| iné s menom | 173 927 (amenity 44 704, shop 26 266, natural 19 977, information 19 144, historic 9 908, …) |
| bez mena, bez čísla | 1 598 250 |

Celý región v pôvodnom (nefiltrovanom) indexe: 3,9 mil. POI (reštaurácie 249 tis., kostoly
174 tis., pamätníky 132 tis., kaviarne, obchody, škôlky…) a 417 tis. lokalít/samôt — šum pre
vyhľadávanie miest.

## Konverzia (`_lab/scripts/convert.php`) — finálna verzia

Vstup na stdin, výstup NDJSON pre Meilisearch + SQLite tabuľka čísel domov.

```
zstd -dc slovakia.jsonl.zst | php -d memory_limit=6G convert.php out/slovakia.ndjson out/slovakia.sqlite
```

Pravidlá:
- **Obce**: `osm_key=place` a `osm_value` ∈ city 90, town 70, village 50, suburb 45,
  borough 45, quarter 40, neighbourhood 30, hamlet 30 (číslo = základ `rank`).
  `locality`, `isolated_dwelling` vynechané.
- **POI** (len ciele ciest): tourism ∈ hotel, guest_house, hostel, motel, apartment, chalet,
  alpine_hut, camp_site, caravan_site, attraction, museum, theme_park, zoo, aquarium;
  leisure ∈ water_park, stadium, resort, marina, golf_course; aeroway=aerodrome;
  railway=station; amenity ∈ hospital, university, college, ferry_terminal, bus_station;
  historic=castle. Základ `rank` 20.
- **Ulice**: `osm_key=highway` s menom → jedna na (krajina, obec, meno); ulica známa len
  z adries tiež vznikne. `rank` 25 000.
- `rank` = základ × 1000 + `round(importance × 999)` (Roma 90 767, Rimini 90 639).
- **Dedup obcí**: rovnaké (krajina, meno) do 5 km = jedna, ostáva vyšší `rank`, PSČ sa doplní.
  POI: rovnaké meno + typ + súradnice zaokrúhlené na 0,01°.
- `names` = miestne meno + `name:{sk,en,de,hu,cs,pl,hr,sl,it}` (bez duplicít) — všetky
  v jednom poli, aby preklad vážil rovnako ako miestne meno.
- `city` = obec z adresy (`city` ?? `town` ?? `village` ?? `locality`); pri obci samotnej
  nadradená obec alebo prázdne.
- `street_key` = prvých 15 hex znakov `xxh64("CC|obec|ulica")` ako int; id ulice v indexe
  `s<street_key>`. Adresa bez ulice (len `address.place`) → ulica = názov miesta.

### Dokument v Meilisearchi

```json
{"id":"R109166","kind":"place","type":"city","name":"Wien",
 "names":["Wien","Viedeň","Vienna","Bécs","Vídeň","Wiedeń","Beč","Dunaj"],
 "city":"","region":"","cc":"AT","postcode":null,
 "_geo":{"lat":48.2083537,"lng":16.3725042},"rank":90xxx}
```
`kind` ∈ place | street | poi. Priemerná veľkosť ~290 B.

### Tabuľka čísel domov

```sql
CREATE TABLE house (street_key INTEGER NOT NULL, number TEXT NOT NULL, postcode TEXT,
                    lat REAL NOT NULL, lon REAL NOT NULL);
CREATE INDEX house_street ON house(street_key, number);
```

Hľadanie: token, ktorý vyzerá ako číslo domu (`^\d+[a-zA-Z]?(/\d+[a-zA-Z]?)?$`), sa z dotazu
vyberie → Meilisearch hľadá zvyšok → pre nájdené `kind=street` sa robí
`SELECT number, lat, lon FROM house WHERE street_key = ? AND number LIKE '12%' LIMIT 5`.
Slovenské čísla „súpisné/orientačné“ (`606/42`) — `LIKE '12%'` nájde `12/31` aj `12/25`;
treba ešte riešiť hľadanie podľa orientačného čísla (časť za lomkou).
