#!/bin/bash
set -e
cd "$(dirname "$0")/.."
while kill -0 13657 2>/dev/null; do sleep 20; done
echo "=== scorer finished ==="
php artisan civiclens:decide-fields
echo "=== health ==="
php artisan civiclens:health || true
