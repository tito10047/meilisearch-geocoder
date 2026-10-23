# Valhalla (trasy) — mimo rozsahu knižnice, pre Tankovač

Image `ghcr.io/valhalla/valhalla-scripted:latest`, binárky `valhalla_build_config`,
`valhalla_build_admins`, `valhalla_build_tiles`, `valhalla_build_extract`, `valhalla_service`.
Skripty `_lab/scripts/build-valhalla.sh`, `serve-valhalla.sh`, `bench-valhalla.py`.
Dáta `_data/valhalla/{sk,all}` (all = 9 krajín).

## Build

`valhalla_build_tiles` berie viac PBF naraz (netreba osmium merge).

| | SK | 9 krajín (~11,4 GB PBF) |
|---|---|---|
| Čas | 46 s | **34 min** (`CONC=6`) |
| `tiles.tar` | 347 MB | **13 GB** (4313 dlaždíc) |
| `admins.sqlite` | 10 MB | 134 MB |

Chyby `NOT NULL constraint failed: admin_access.admin_id … Philippines/Romania/…` sa týkajú
krajín mimo extraktu — ignorovať.

## Beh — nastavenia zistené testom

```
valhalla_build_config --mjolnir-tile-extract /data/tiles.tar --mjolnir-admin /data/admins.sqlite \
  --mjolnir-max-cache-size 20000000 --mjolnir-use-lru-mem-cache False \
  --mjolnir-global-synchronized-cache False --thor-clear-reserved-memory True \
  --thor-max-reserved-labels-count-astar 100000 --thor-max-reserved-labels-count-bidir-astar 100000
MALLOC_ARENA_MAX=2 valhalla_service conf.json 1
```

- `global_synchronized_cache: True` → **segfault (139) pri súbežných požiadavkách**
  (postupne 870 trás bez problému).
- Cache je na workera (loki/thor/odin) aj vlákno: 100 MB → OOM pri 300 MB; 20 MB stačí.
- **1 vlákno**: s 2 vláknami a 4 súbežnými dotazmi OOM aj pri 300 MB. S 1 vláknom limit
  300 MB, peak 227–251 MiB, 286–291/300 trás OK (zvyšok = centrum Viedne, viď nižšie),
  medián 318–336 ms (fronta; jedna trasa ~100–250 ms).
- Jedna trasa, 1 vlákno: BA–KE s alternatívami ~55 MiB, Gdansk–Split s alternatívami ~100 MiB.
- Pôvodne s SK a LRU + global cache: anon ~50 MiB pre SK.

## Správanie API

- **Stephansplatz (48.2082, 16.3738) → kamkoľvek: 442 „No path could be found“** — bod padne
  do pešej zóny. `minimum_reachability: 500` nepomohlo, **`radius: 200` na lokácii áno**.
- Mimo dát: 171 „No suitable edges near location“ → v aplikácii `ProviderUnavailableException`
  (prevezme záložný poskytovateľ), nie „trasa neexistuje“.
- **Krajiny**: `admin_crossings: true` → `trip.legs[].summary.admins` (`country_code` ISO
  alpha-2, `state_code/text`) a `admin_crossings` (`from_admin_index`, `to_admin_index`,
  `begin_shape_index`, `end_shape_index`). Nie v `trip.summary`.
- **Mýto**: `directions_type: maneuvers` → manévre s `toll: true` (kľúč chýba, keď nie),
  `length` (km), `begin/end_shape_index`. BA → Split 801 km: SK 20 / HU 247 / SI 17 / HR 516 km,
  spoplatnené SK 15 / HU 163 / SI 16 / HR 477 km. Po manévroch je to hrubé (manéver cez
  hranicu celý do krajiny začiatku) — presne cez body trasy (`shape`, polyline6) a
  `admin_crossings`, alebo `trace_attributes` s `edge.toll`, `edge.length`. Neoverené proti
  GraphHopperu.
- Alternatívy (`alternates: 2`) len medzi 2 bodmi, ako pri iných enginoch.
