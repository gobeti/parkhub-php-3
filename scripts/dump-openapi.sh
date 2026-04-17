#!/usr/bin/env bash
#
# Dump the current PHP OpenAPI spec into docs/openapi/php.json via
# `php artisan scramble:export`.
#
# Intended for developer use before committing an API change: run this,
# commit the updated docs/openapi/php.json alongside the code, and the
# PR diff will show the contract change.

set -euo pipefail

OUT="docs/openapi/php.json"

mkdir -p "$(dirname "$OUT")"

php artisan scramble:export --path="$OUT"

# Pretty-print + normalise key ordering so diffs stay readable
jq -S '.' "$OUT" > "$OUT.tmp" && mv "$OUT.tmp" "$OUT"

echo "wrote $OUT ($(wc -c < "$OUT") bytes, $(jq '.paths | length' "$OUT") paths)"
