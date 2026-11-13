#!/bin/bash
set -e
cd "$(dirname "$0")/.."
# Wait on the scorer by PID, never by pattern: a pgrep for the pattern matches
# this script too, which once blocked a chain for hours.
while kill -0 31400 2>/dev/null; do sleep 60; done
echo "=== scoring finished ==="
tail -2 /tmp/score-newkinds.log
echo "=== decide ==="
php artisan civiclens:decide-fields
echo "=== health ==="
php artisan civiclens:health || true
echo "=== publish ==="
php artisan civiclens:publish 2026-09-04-a --publish \
  --notes="Scored the candidates generated after the court kinds were added, including 2,601 dispositions. Table detection reduced to the tranches that target values the locator could not find."
echo "=== worker up on tables + tables-candidates only ==="
launchctl load -w "$HOME/Library/LaunchAgents/com.civiclens.queue-worker.plist"
sleep 10
launchctl list | grep queue-worker
echo "=== done ==="
