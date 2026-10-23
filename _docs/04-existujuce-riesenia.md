# Existujúce riešenia (prieskum 2026-10-22)

Záver: **nič také ako táto knižnica neexistuje.** V PHP sú len klienti cudzích služieb;
jediný Meilisearch + OSM geokóder je jednoúčelová vietnamská inštalácia v Node.js.

## PHP / Packagist

- **geocoder-php/Geocoder** (https://github.com/geocoder-php/Geocoder, pôvodne `willdurand/geocoder`)
  — najrozšírenejšia PHP knižnica, ale **nie geokóder, len spoločné rozhranie**:
  `geocodeQuery()` / `reverseQuery()`, výsledok `Address`, provideri ako samostatné balíky
  (Google, Nominatim, Photon, Pelias, Mapbox, HERE, OpenCage…), `Chain`, cache, rate limit;
  Symfony `BazingaGeocoderBundle`. Robí jednorazové hľadanie, **nie písanie s návrhmi**.
  ⇒ Pre nás **miesto zapojenia**: provider (napr. `MeilisearchProvider`) volá Meilisearch
  priamo, bez HTTP servera navyše.
- Provideri `geocoder-php/photon-provider` (415 tis. stiahnutí), `nominatim-provider` (5,2 mil.),
  `pelias-provider` (234 tis.), MIT, aktualizované 2025-11 — HTTP klienti (`geocoder-php/common-http`).
  Ak bude naše HTTP API **kompatibilné s Photonom**, funguje `photon-provider` bez zmeny.
- Ďalší klienti Nominatimu: maxhelias/php-nominatim, riverside/php-nominatim,
  pear/Services_Openstreetmap, fkrauthan/FkrNominatimBundle (Symfony 2, mŕtvy od 2014),
  jbohme/nominatim-laravel, marshmallow/laravel-nominatim.
- Jediné offline: **arielmejiadev/atlas** (Laravel, SQLite z GeoNames — len stredy miest
  a US ZIP, bez ulíc, bez autocomplete); daveross/offline-country-reverse-geocoder (len krajina).
- Na Packagiste nič pre „meilisearch geocoder“, „meilisearch geo“, „typesense geo“.
- Čítanie OSM v PHP: **franksierra/osm-pbf** (MIT, 10★, `google/protobuf`, surové bloky,
  bez geometrie a hierarchie), kicken/osm-parser (2023), staré XML parsery. ⇒ Photon výpisy
  sú jednoduchšia cesta.

## Iné jazyky / engine

| Projekt | Jazyk / úložisko | Stav | Poznámka |
|---|---|---|---|
| **komoot/photon** | Java 21, OpenSearch | aktívny, 3,1k★, Apache-2.0 | referencia pre API (`docs/api-v1.md`); ~380 MiB už pre SK |
| **Fanna1119/geors** | Rust, Tantivy, mmap index na krajinu | týždne starý, 23★, MIT | klon Photonu, PBF aj Photon výpisy, API ako Photon (port 2322); import DE ~1 GB RAM, index DE 5,6 GB; čísla domov nespomína |
| **open-geocode-rs/open-geocode** | Rust, „Pack“ (mmap + Tantivy + H3) | veľmi mladý, 5★, MIT | ~30 MB procesu pre Ontario (2 mil. adries) |
| deangrant/hexplace | Rust, Tantivy + H3 | 0★ | regionálne extrakty |
| Mokto/opengeocoding | Rust + libpostal | experimentálny, PolyForm Shield | svet: 6,3 GiB RAM pri behu |
| ellenhp/airmail | Rust, Tantivy | skoré štádium | ~16 GB RAM na indexovanie |
| addok | Python + Redis (celý index v RAM) | zrelý, MIT | Francúzsko ~6 GB RAM, nevie OSM |
| Nominatim | PostgreSQL | | planéta 128 GB+ RAM |
| Pelias | Node + Elasticsearch | 3,6k★ | ťažký |
| trangvh87/osm-geocoder-vn | Node proxy → Meilisearch (470 tis. dok. z Nominatimu) → fallback Nominatim | 0★ | **najbližšie myšlienke**, ale jedna krajina a potrebuje celý Nominatim |
| klokantech/osmnames-sphinxsearch | Python, Sphinx | opustený 2023 | len mená miest |
| dunkelstern/osmgeocoder | Python, PostGIS | opustený 2022 | |
| morta5/adressSuche | Python, FastAPI + SQLite FTS5 | 3★, AGPL | len Nemecko; SQLite pre adresy |
| typesense/showcase-address-autocomplete | demo | 2024 | 15 tis. adries Boston |
| climate-app/meilisearch-address-db | shell | 2025 | NSW adresy do Meilisearchu |

Meilisearch má `_geo`, `_geoRadius`, `_geoBoundingBox`, `_geoPolygon`, triedenie
`_geoPoint` — žiadny oficiálny návod na OSM geokódovanie.

## Medzera, ktorú knižnica zaplní

1. Prvý geokóder **v PHP** (import, index, dotaz) — nie klient cudzej služby.
2. Ľahká alternatíva k Photonu/Nominatimu/Peliasu pre PHP hostingy, s Meilisearchom,
   ktorý už mnohí majú.
3. HTTP API **kompatibilné s Photonom** → funguje `geocoder-php/photon-provider` a widgety.
4. Provider pre geocoder-php, Symfony bundle, prípadne Laravel balík.

Inšpirácia: geors (stačí Photon výpis, rozsah funkcií), osm-geocoder-vn (tvar dokumentov),
Photon `docs/api-v1.md` (kontrakt API). Kópie geors a open-geocode sú v `_data/reference/`.
