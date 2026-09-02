#!/bin/bash
set -e
cd "$(dirname "$0")/.."
# Wait on the generator loop by PID, never by pattern: a pgrep for the pattern
# matches this script too, which once blocked a chain for hours.
while kill -0 30057 2>/dev/null; do sleep 20; done
echo "=== candidates finished ==="
tail -3 /tmp/gen-candidates.log
echo "=== score ==="
php artisan civiclens:score-fields --limit=200000 --sync
echo "=== decide ==="
php artisan civiclens:decide-fields
echo "=== health ==="
php artisan civiclens:health || true
echo "=== publish ==="
php artisan civiclens:publish 2026-09-02-f --publish \
  --notes="Review candidates generated over the whole current corpus, including the judgment pages extracted today, then scored and decided per publisher x script x kind of value."
echo "=== worker back up, on the fixed job class ==="
launchctl load -w "$HOME/Library/LaunchAgents/com.civiclens.queue-worker.plist"
sleep 8
php artisan queue:retry all
echo "=== done ==="
