#!/bin/bash
# build-variant.sh NAME NDJSON_DIR PORT — fresh Meilisearch v1.35.1 in _data/exp-i18n/meili-NAME,
# index "geo" with the geo4 settings (every field displayed), all NDJSON files pushed in 50k-line
# chunks; prints indexing time, data.ms size and memory, then search latency and memory.
# SEARCHABLE='["names","cities"]' overrides the searchable attributes.
set -euo pipefail
cd "$(dirname "$0")/../.."
name=$1; dir=$2; port=$3
key=osmlab-test-key-123456
data=$PWD/_data/exp-i18n/meili-$name
api() { curl -sf -X "$1" "http://127.0.0.1:$port$2" -H "Authorization: Bearer $key" -H 'Content-Type: application/json' "${@:3}"; }
wait_tasks() { while api GET '/tasks?statuses=enqueued,processing&limit=1' | grep -q '"uid"'; do sleep 2; done; }

docker rm -f "mi-$name" >/dev/null 2>&1 || true
mkdir -p "$data"
docker run -d --name "mi-$name" --user "$(id -u):$(id -g)" --memory=4g --memory-swap=4g --cpus=4 \
  -p "127.0.0.1:$port:7700" -v "$data:/meili_data" -e MEILI_MASTER_KEY=$key -e MEILI_NO_ANALYTICS=true \
  getmeili/meilisearch:v1.35.1 >/dev/null
until api GET /health >/dev/null 2>&1; do sleep 1; done

api POST /indexes -d '{"uid":"geo","primaryKey":"id"}' >/dev/null
# geo4 settings, but every attribute displayed so the translations come back
tr -d '\n' < _lab/scripts/geo4-settings.json | sed 's/"displayedAttributes": \[[^]]*\]/"displayedAttributes": ["*"]/' \
  | sed "s/\"searchableAttributes\": \[[^]]*\]/\"searchableAttributes\": ${SEARCHABLE:-[\"names\", \"city\"]}/" \
  | api PATCH /indexes/geo/settings --data-binary @- >/dev/null
wait_tasks

t0=$(date +%s)
for f in "$dir"/*.ndjson; do
  split -l 50000 --filter="curl -sf -X POST http://127.0.0.1:$port/indexes/geo/documents -H 'Authorization: Bearer $key' -H 'Content-Type: application/x-ndjson' --data-binary @- -o /dev/null" "$f"
done
wait_tasks
echo "$name: indexed in $(( $(date +%s) - t0 ))s"
api GET '/tasks?statuses=failed&limit=1' | head -c 300; echo
api GET /indexes/geo/stats; echo
du -sh "$data/data.ms" | sed "s/^/$name: data.ms /"
cg=$(docker inspect -f '{{.Id}}' "mi-$name")
stat() { f=$(find /sys/fs/cgroup -type d -name "*$cg*" | head -1); echo "$1 anon=$(( $(awk '/^anon /{print $2}' $f/memory.stat) >> 20 ))MiB peak=$(( $(cat $f/memory.peak) >> 20 ))MiB"; }
stat "$name after indexing:"

# search: restart (fresh process, cold), then type-ahead prefixes like bench-meili.py
docker restart "mi-$name" >/dev/null; until api GET /health >/dev/null 2>&1; do sleep 1; done
python3 -I - "$port" <<'EOF'
import json, random, statistics, sys, time, urllib.request
port = sys.argv[1]
words = ["Bratislava", "Košice", "Žilina", "Banská Bystrica", "Hlavná Košice", "Mlynské nivy Bratislava", "Praha",
         "Brno", "Wien", "Graz", "Budapest", "Kraków", "Warszawa", "Zagreb", "Split", "Ljubljana", "München",
         "Berlin", "Roma", "Venezia", "Lignano", "viedeň", "mníchov", "benátky", "Stephansplatz Wien", "Lidl Poprad"]
random.seed(1)
lat = []
for _ in range(1500):
    w = random.choice(words); q = w[:random.randint(3, len(w))]
    r = urllib.request.Request(f"http://127.0.0.1:{port}/indexes/geo/search", json.dumps({"q": q, "limit": 7}).encode(),
                               {"Content-Type": "application/json", "Authorization": "Bearer osmlab-test-key-123456"})
    t = time.perf_counter(); json.load(urllib.request.urlopen(r)); lat.append(time.perf_counter() - t)
lat.sort()
print(f"search n={len(lat)} median={statistics.median(lat)*1000:.1f}ms p95={lat[int(.95*len(lat))]*1000:.1f}ms")
EOF
cg=$(docker inspect -f '{{.Id}}' "mi-$name")
stat "$name after search:"
