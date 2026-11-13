#!/bin/bash
set -e
cd "$(dirname "$0")/.."
# Wait on the publish chain by PID, never by pattern: a pgrep for the pattern
# matches this script too, which once blocked a chain for hours.
while kill -0 34178 2>/dev/null; do sleep 30; done
echo "=== publish chain finished ==="
tail -20 /tmp/after-candidates.log

# The worker is reloaded by that chain and owns the CPU again, so pause it:
# one writer at a time, or candidate generation and the queue contend for the
# SQLite lock and both report failures that look like unrelated bugs.
launchctl unload -w "$HOME/Library/LaunchAgents/com.civiclens.queue-worker.plist" || true
sleep 3

echo "=== generating candidates for disposition and finding_span ==="
# Guarded on `created`, not on `pages`. The command always finds pages to
# examine whether or not they yield anything, so a pages==0 guard never fires
# and the loop ran 60 passes over a corpus it had already walked twice.
for i in $(seq 1 40); do
  out=$(php artisan civiclens:generate-review-candidates --limit=2000 2>&1)
  created=$(printf '%s' "$out" | tr -d '\n ' | sed -n 's/.*"created":\([0-9]*\).*/\1/p')
  echo "pass $i: created=${created:-?}"
  [ "${created:-0}" = "0" ] && { echo "exhausted"; break; }
done

echo "=== what the new kinds yielded ==="
php artisan tinker --execute='
use Illuminate\Support\Facades\DB;
foreach (["disposition", "finding_span"] as $k) {
    printf("  %-14s %6d candidates, %6d with a score, %6d accepted\n", $k,
        DB::table("extraction_fields")->where("field_key", $k)->count(),
        DB::table("extraction_fields")->where("field_key", $k)->whereNotNull("nonconformity_score")->count(),
        DB::table("extraction_fields")->where("field_key", $k)->where("decision", "accepted")->count());
}'

echo "=== worker back up ==="
launchctl load -w "$HOME/Library/LaunchAgents/com.civiclens.queue-worker.plist"
echo "=== done ==="
