#!/bin/bash
# Runs the cycle the re-read leaves owed, in the only order that pays once.
#
#   candidates  the recovered pages hold values nothing has offered a reviewer
#   score       both the new values and the 739 judged ones carrying a stale
#               reader, in one pass rather than two
#   decide      apply thresholds; publishing stays a separate, deliberate act
# Stop at the first failure. The score pass died of memory exhaustion at page
# 100 of 3,494 and the chain carried on regardless — deciding thresholds on
# scores that had not been taken, and only the publish gate caught it.
set -eu
cd "$(dirname "$0")/.."
log() { printf '\n=== %s %s\n' "$(date '+%H:%M:%S')" "$1"; }

until ! pgrep -f "[r]eread-abstained" >/dev/null; do sleep 60; done
log "re-read finished"
tail -1 storage/logs/reread-abstained.log

log "generating candidates"
php artisan civiclens:generate-review-candidates --limit=12000

log "scoring (unscored first, then the stale readers)"
php artisan civiclens:score-fields --limit=12000 --sync

log "deciding"
php artisan civiclens:decide-fields

log "calibration"
php artisan civiclens:calibration-report

# Health is reported, not gated on. Its one complaint before a publish is
# drift — the snapshot being behind what is certified — and publishing is what
# resolves that, so blocking on it blocks the fix for the condition. The
# post-publish health check below is the one that means something: drift there
# is a real mismatch.
log "health before publishing"
php artisan civiclens:health || true

# Published only if health passes. It exits non-zero on drift or breakage, and
# a snapshot built on a corpus that fails its own checks is worse than a stale
# one: the stale one is at least consistent.
LABEL="$(date '+%Y-%m-%d')-recovered"
log "publishing $LABEL"
php artisan civiclens:publish "$LABEL" \
  --notes="Pages that were abstained and stored nothing have been read again: sideways pages straightened, and pages whose mean confidence fell short now kept with each value judged on its own reading rather than the page average. Scores re-taken corpus-wide under the current reader." \
  --publish

log "post-publish health"
php artisan civiclens:health

# The worker was unloaded so the re-read and the score pass had the CPU to
# themselves. Bring it back with ingestion first: 2,490 fetched judgments are
# almost entirely native text and read in hours, while table detection has
# thousands of pages left at two minutes each.
log "restarting the worker, judgments first"
cp ops/launchd/com.civiclens.queue-worker.plist "$HOME/Library/LaunchAgents/"
launchctl unload "$HOME/Library/LaunchAgents/com.civiclens.queue-worker.plist" 2>/dev/null
launchctl load "$HOME/Library/LaunchAgents/com.civiclens.queue-worker.plist"
sleep 5
launchctl list | grep civiclens.queue-worker || echo "worker did not start"
log "done"
