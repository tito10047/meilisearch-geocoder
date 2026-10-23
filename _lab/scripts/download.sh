#!/bin/bash
set -e
cd "$1"
for c in slovakia czech-republic austria hungary poland croatia slovenia; do
  [ -s pbf/$c.osm.pbf ] || curl -sfL -o pbf/$c.osm.pbf https://download.geofabrik.de/europe/$c-latest.osm.pbf
  [ -s photon-dump/$c.jsonl.zst ] || curl -sfL -o photon-dump/$c.jsonl.zst https://download1.graphhopper.com/public/europe/$c/photon-dump-$c-1.0-latest.jsonl.zst
  echo "done $c"
done
