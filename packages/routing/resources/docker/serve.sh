#!/bin/bash
# Serves routes from the tiles built by build.sh, tuned for a small server.
#
# Runs inside the Valhalla image with the built data mounted (read-only is enough) at /data.
# The defaults are the settings that held under concurrent load with 9 countries in 300 MB:
#   - tiles are read from the mmap'ed tiles.tar, not from a tile directory;
#   - a small cache (20 MB), allocated per worker (loki, thor, odin) and per thread;
#   - no LRU and no global synchronized cache: the global cache crashed (exit 139) under
#     concurrent requests;
#   - 1 thread: with 2 threads and 4 concurrent requests the service ran out of memory;
#   - MALLOC_ARENA_MAX=2 keeps glibc from reserving an arena per thread.
set -euo pipefail

: "${DATA:=/data}"
: "${THREADS:=1}"
: "${CACHE:=20000000}"
: "${LABELS:=100000}"
: "${PORT:=8002}"

if [ ! -f "$DATA/tiles.tar" ] || [ ! -f "$DATA/admins.sqlite" ]; then
    echo "$DATA/tiles.tar and $DATA/admins.sqlite are missing, build them with build.sh first." >&2
    exit 1
fi

config=$(mktemp)
valhalla_build_config \
    --mjolnir-tile-dir "$DATA/none" \
    --mjolnir-tile-extract "$DATA/tiles.tar" \
    --mjolnir-admin "$DATA/admins.sqlite" \
    --mjolnir-max-cache-size "$CACHE" \
    --mjolnir-use-lru-mem-cache False \
    --mjolnir-global-synchronized-cache False \
    --thor-clear-reserved-memory True \
    --thor-max-reserved-labels-count-astar "$LABELS" \
    --thor-max-reserved-labels-count-bidir-astar "$LABELS" \
    --httpd-service-listen "tcp://*:$PORT" > "$config"

export MALLOC_ARENA_MAX="${MALLOC_ARENA_MAX:-2}"
exec valhalla_service "$config" "$THREADS"
