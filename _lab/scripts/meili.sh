#!/bin/bash
# meili.sh METHOD PATH [curl args...]
m=$1; p=$2; shift 2
curl -s -X $m "http://127.0.0.1:${PORT:-7711}$p" -H "Authorization: Bearer osmlab-test-key-123456" -H 'Content-Type: application/json' "$@"
