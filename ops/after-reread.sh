#!/bin/bash
# Runs the cycle the re-read leaves owed, in the only order that pays once.
#
#   candidates  the recovered pages hold values nothing has offered a reviewer
#   score       both the new values and the 739 judged ones carrying a stale
#               reader, in one pass rather than two
#   decide      apply thresholds; publishing stays a separate, deliberate act
set -u
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

log "health"
if ! php artisan civiclens:health; then
    log "health reported a problem — NOT publishing"
    exit 1
fi

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
log "done"
