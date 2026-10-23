#!/bin/bash
# Runs inside the valhalla image: /pbf (ro) -> /data (tiles + tar)
set -e
cd /data
valhalla_build_config --mjolnir-tile-dir /data/tiles --mjolnir-tile-extract /data/tiles.tar \
  --mjolnir-admin /data/admins.sqlite --mjolnir-timezone /data/tz.sqlite \
  --mjolnir-concurrency ${CONC:-8} > valhalla-build.json
date; valhalla_build_admins -c valhalla-build.json /pbf/*.osm.pbf
valhalla_build_tiles -c valhalla-build.json /pbf/*.osm.pbf
date; valhalla_build_extract -c valhalla-build.json -v
rm -rf /data/tiles
date; ls -la /data
