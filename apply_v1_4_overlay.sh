#!/usr/bin/env bash
set -euo pipefail
SRC="${1:-}"
DST="${2:-$(pwd)}"
if [ -z "$SRC" ]; then
  echo "Usage: bash apply_v1_4_overlay.sh /path/to/commercehub-v1.4-hotfix [/path/to/laravel]"
  exit 1
fi
for d in app database resources routes docs scripts; do
  if [ -d "$SRC/$d" ]; then
    cp -R "$SRC/$d" "$DST/"
  fi
done
echo "Overlay copied. Now run: php artisan optimize:clear && php artisan migrate"
