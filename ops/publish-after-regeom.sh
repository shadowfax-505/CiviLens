#!/bin/bash
set -e
cd "$(dirname "$0")/.."
while kill -0 24402 2>/dev/null; do sleep 30; done
echo "=== scorer finished ==="
tail -2 /tmp/score-held.log
echo "=== decide ==="
php artisan civiclens:decide-fields
echo "=== health (drift alone does not block; publishing resolves it) ==="
php artisan civiclens:health || true
echo "=== publish ==="
php artisan civiclens:publish 2026-09-02-d --publish \
  --notes="Re-scored every value whose page gained word geometry after it was last scored: 96,529 scores retaken. Locator span raised from four words to sixteen so multi-word court values can be found at all."
echo "=== figures ==="
php artisan civiclens:publisher-report || true
