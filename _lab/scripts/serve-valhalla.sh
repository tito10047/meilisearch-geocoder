#!/bin/bash
# Low-memory serving config: tiles from the mmap'ed tar, small cache, few threads.
# LRU=True switches on the LRU cache (segfaulted under concurrent load in v3.x with the global cache).
set -e
valhalla_build_config --mjolnir-tile-dir /data/none --mjolnir-tile-extract /data/tiles.tar \
  --mjolnir-admin /data/admins.sqlite --mjolnir-max-cache-size ${CACHE:-100000000} \
  --mjolnir-use-lru-mem-cache ${LRU:-False} --mjolnir-global-synchronized-cache ${GLOBAL:-False} \
  --thor-clear-reserved-memory True --thor-max-reserved-labels-count-astar ${LABELS:-100000} \
  --thor-max-reserved-labels-count-bidir-astar ${LABELS:-100000} \
  --httpd-service-listen tcp://*:8002 > /tmp/serve.json
export MALLOC_ARENA_MAX=${ARENAS:-2}
exec valhalla_service /tmp/serve.json ${THREADS:-2}
