#!/bin/bash
# load.sh file.ndjson — push in 50k-line chunks, wait for all tasks, report time and peak memory
set -e
cd "$(dirname "$0")/.."
f=$1; t0=$(date +%s)
split -l 50000 --filter="curl -s -X POST \"http://127.0.0.1:${PORT:-7711}/indexes/${INDEX:-geo}/documents\" -H \"Authorization: Bearer osmlab-test-key-123456\" -H \"Content-Type: application/x-ndjson\" --data-binary @- -o /dev/null" "$f"
while scripts/meili.sh GET '/tasks?statuses=enqueued,processing&limit=1' | grep -q '"uid"'; do sleep 5; done
echo "indexed in $(( $(date +%s) - t0 ))s"
scripts/meili.sh GET '/tasks?statuses=failed&limit=3' | head -c 400; echo
scripts/meili.sh GET /indexes/${INDEX:-geo}/stats | head -c 300; echo
du -sh ${DATA:-meili-data}/data.ms
scripts/mem.sh ${CONTAINER:-ms}
