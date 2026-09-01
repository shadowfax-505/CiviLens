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
php artisan civiclens:health
log "done"
