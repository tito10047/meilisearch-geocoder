#!/bin/bash
# Builds the routing tiles from OpenStreetMap extracts.
#
# Runs inside the Valhalla image: every *.osm.pbf in /pbf (read-only) becomes /data/tiles.tar
# and /data/admins.sqlite (the country borders, needed for the countries and tolls per country).
# valhalla_build_tiles takes several PBF files at once, they need no merging.
# Measured: Slovakia 46 s / 347 MB, 9 central European countries (11.4 GB of PBF) 34 min / 13 GB
# with CONCURRENCY=6. Errors "NOT NULL constraint failed: admin_access.admin_id" name countries
# outside the extracts and can be ignored.
set -euo pipefail

: "${PBF:=/pbf}"
: "${DATA:=/data}"
: "${CONCURRENCY:=$(nproc)}"

shopt -s nullglob
extracts=("$PBF"/*.osm.pbf)
if [ ${#extracts[@]} -eq 0 ]; then
    echo "No *.osm.pbf in $PBF." >&2
    exit 1
fi

cd "$DATA"
valhalla_build_config \
    --mjolnir-tile-dir "$DATA/tiles" \
    --mjolnir-tile-extract "$DATA/tiles.tar" \
    --mjolnir-admin "$DATA/admins.sqlite" \
    --mjolnir-timezone "$DATA/tz.sqlite" \
    --mjolnir-concurrency "$CONCURRENCY" > "$DATA/valhalla-build.json"

date; valhalla_build_admins -c "$DATA/valhalla-build.json" "${extracts[@]}"
date; valhalla_build_tiles -c "$DATA/valhalla-build.json" "${extracts[@]}"
date; valhalla_build_extract -c "$DATA/valhalla-build.json" -v
# the tar holds every tile, the directory would only double the disk space
rm -rf "$DATA/tiles"
date; ls -la "$DATA"
