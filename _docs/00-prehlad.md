# Meilisearch geocoder — prehľad

Stav: **výskum a merania hotové (2026-10-22), kód knižnice ešte neexistuje.**

## O čo ide

Open-source **PHP knižnica**, ktorá z dát OpenStreetMap postaví vlastný geokóder
(adresa/miesto → súradnice, písanie s návrhmi) nad **Meilisearchom** a čísla domov drží
v **SQLite/MariaDB cez PDO**. Bez Javy, Elasticsearchu, PostGIS a bez cudzej služby.

Vznikla z projektu **Tankovač** (`~/phpProjects/sukromne/tankovac`, kalkulačka nákladov
na palivo): tam sa adresy hľadajú cez GraphHopper/openrouteservice s dennou kvótou a autor
chce bežať na vlastnom serveri. Pôvodná analýza je v Tankovači v `_docs/self-hosting-geo.md`
(a skripty v `_docs/geo-lab/`); všetko podstatné je skopírované sem.

## Prečo

- Na Packagiste **nič také nie je** — len HTTP klienti cudzích služieb (geocoder-php provideri
  pre Photon, Nominatim, Pelias…). Viď `04-existujuce-riesenia.md`.
- Bežné self-hosted geokódery sú na malý server ťažké: Photon (Java + OpenSearch) ~380 MiB
  už pre samotné Slovensko, Nominatim/Pelias gigabajty. Viď `01-merania.md`.
- Meilisearch je v PHP svete bežný (mnoho projektov ho už má) a **pri hľadaní** potrebuje
  ~50 MiB vlastnej pamäte aj pre 9 krajín (4,4 mil. dokumentov).

## Kľúčové zistenia (detaily v ďalších súboroch)

1. **Zdroj dát: Photon JSON výpisy** (Nominatim Dump File, formát CC0, dáta ODbL) — týždenne,
   po krajinách, adresy už spracované (obec, ulica, PSČ, mená v jazykoch, `importance`).
   Netreba parsovať PBF v PHP. Viď `02-data-a-format.md`.
2. **Rozdelenie dát**: obce, ulice a vybrané POI → Meilisearch; čísla domov → SQL tabuľka.
   Pre 9 krajín: 4,42 mil. dokumentov (index 3,8 GB) + 44,2 mil. čísel domov (SQLite 2,9 GB).
3. **Hľadanie je lacné, indexovanie drahé**: pri hľadaní ~50 MiB anon, medián 2,4 ms;
   pri indexovaní rastie pamäť s indexom (~2 GB pri celom regióne, pri 1 GB limite OOM).
   ⇒ knižnica musí podporiť **stavbu indexu mimo produkčného servera** (alebo aspoň po častiach).
4. **Ranking**: 42/43 testovacích dotazov na 1. mieste po úpravách — mená vo všetkých jazykoch
   v jednom poli, `rank` (typ miesta × 1000 + importance) **pred** `attribute`. Viď
   `03-meilisearch-index-a-ranking.md`.
5. **Trasy (Valhalla) sú súčasťou projektu** (rozhodnuté 2026-10-23): balík `tito10047/routing`
   nad vlastnou Valhallou nahradí GraphHopper/openrouteservice; merania v `06-valhalla.md`.
   Aktuálny návrh celku (balíky, API, postup): **`08-analyza-a-verejne-api.md`**.

## Rozhodnutia používateľa

- Rozpočet RAM na serveri pre všetky geo služby spolu: **1,2 GB** (server 8 GB, zdieľaný).
- Región Tankovača: **SK, CZ, AT, HU, PL, HR, SI, DE, IT**.
- Photon zamietnutý (pamäť), vlastné riešenie Meilisearch + SQL odsúhlasené.
- Najprv **knižnica**, až potom napojenie do Tankovača.

## Obsah `_docs`

| Súbor | Čo |
|---|---|
| `01-merania.md` | všetky merania (Photon, Meilisearch, SQLite, Valhalla), metodika |
| `02-data-a-format.md` | Photon výpisy, ich formát, konverzia, schéma dokumentov a tabuľky |
| `03-meilisearch-index-a-ranking.md` | nastavenia indexu, ranking, test kvality, slepé uličky |
| `04-existujuce-riesenia.md` | čo existuje (PHP, Rust, …), geocoder-php, prečo nič nevyhovuje |
| `05-navrh-kniznice.md` | prvý náčrt (prekonaný dokumentom `08`) |
| `06-valhalla.md` | trasy: nastavenia, pasce, krajiny a mýto |
| `07-data-a-skripty.md` | čo je v `_data/` a `_lab/`, ako čo znova spustiť |
| `08-analyza-a-verejne-api.md` | **aktuálny návrh**: rozhodnutia, balíky, verejné API adries aj trás, bundle, postup |
