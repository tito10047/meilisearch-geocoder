#!/bin/bash
# anon (real) and file (reclaimable cache) memory of a container + peak
id=$(docker inspect -f '{{.Id}}' $1); f=$(find /sys/fs/cgroup -type d -name "*$id*" | head -1)
awk '/^(anon|file) /{printf "%s=%.0fMiB ",$1,$2/1048576}' $f/memory.stat; awk '{printf "peak=%.0fMiB ",$1/1048576}' $f/memory.peak 2>/dev/null; grep -E "^oom_kill " $f/memory.events; echo
