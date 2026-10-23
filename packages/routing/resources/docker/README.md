# Valhalla in Docker

A Valhalla server for `tito10047/routing`, with the settings that held under concurrent load
on a small server: 9 central European countries in 300 MB of memory.

| File | What |
|---|---|
| `compose.yaml` | the server (`valhalla`) and the tile build (`build`, profile `build`) |
| `build.sh` | builds `tiles.tar` and `admins.sqlite` from OpenStreetMap extracts |
| `serve.sh` | writes the configuration and starts `valhalla_service` |

The image is pinned by digest: `ghcr.io/valhalla/valhalla-scripted@sha256:eaad372b…`
(Valhalla 3.9.1-618cad5eb, built 2026-10-19). The settings were measured and the test fixtures
recorded with it; check them again before moving to another image.

## Build the tiles

Download the extracts (`*.osm.pbf`, e.g. from [Geofabrik](https://download.geofabrik.de/))
into one directory, then:

```console
VALHALLA_PBF=/srv/osm/pbf VALHALLA_DATA=/srv/valhalla docker compose --profile build run --rm --user "$(id -u):$(id -g)" build
```

`admins.sqlite` holds the borders the countries and tolls per country are computed from, do not
leave it out. Errors `NOT NULL constraint failed: admin_access.admin_id` name countries outside
the extracts and can be ignored.

The build needs far more memory than serving: build elsewhere and copy `tiles.tar` and
`admins.sqlite` to a small server (Valhalla reads the tar through mmap). Memory and time grow
with the region and with `VALHALLA_CONCURRENCY` (the threads of the build):
a lower concurrency needs less memory and takes longer.

| Region | `VALHALLA_CONCURRENCY` | Time | Memory of the build | Data |
|---|---|---|---|---|
| Slovakia | default | 46 s | | 347 MB |
| 9 countries (SK CZ AT HU PL HR SI DE IT, 11.4 GB of PBF) | 6 | 34 min | | 13 GB |
| 10 countries (the 9, Switzerland and Liechtenstein) | 4 | ~60 min | ~6.4 GiB anonymous | |

Give the container (or the machine) that much memory, or lower the concurrency: a build killed
by the OOM killer leaves an incomplete data directory. `VALHALLA_CONCURRENCY` is 4 by default
in `compose.yaml`; `build.sh` alone uses all the processors.

Regions are not named alike everywhere: Geofabrik publishes `europe/switzerland` and
`europe/liechtenstein` as two extracts (download both), while the Photon dump of the geocoder
covering them is one region, `europe/switzerland-liechtenstein`.

## Serve

```console
VALHALLA_DATA=/srv/valhalla docker compose up -d valhalla
curl http://127.0.0.1:8002/status
```

`VALHALLA_PORT` changes the port published on 127.0.0.1 (8002 by default). The data is mounted
read-only.

| Setting | Value | Why |
|---|---|---|
| threads (`THREADS`) | 1 | with 2 threads and 4 concurrent requests the service ran out of memory at 300 MB |
| tile cache (`CACHE`) | 20 MB | the cache is per worker and thread: 100 MB ran out of memory at 300 MB |
| LRU and global synchronized cache | off | the global cache crashed (exit 139) under concurrent requests |
| `MALLOC_ARENA_MAX` | 2 | glibc would reserve an arena per thread |
| `mem_limit` | 300 MB | 9 countries peaked at 251 MiB under load |

With these settings a long route takes 0.1 to 0.25 s; concurrent requests queue (median
0.33 s with 4 at once). Set the timeout of the HTTP client to a few seconds.

## Data

The tiles are built from OpenStreetMap data, © OpenStreetMap contributors, available under
the [Open Database License](https://www.openstreetmap.org/copyright). Applications showing
routes must attribute it.
