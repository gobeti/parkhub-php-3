#!/usr/bin/env bash
#
# Dump the current PHP OpenAPI spec into docs/openapi/php.json via
# `php artisan scramble:export`.
#
# Intended for developer use before committing an API change: run this,
# commit the updated docs/openapi/php.json alongside the code, and the
# PR diff will show the contract change.
#
# The dump always runs against .env.example so developers can't produce
# a snapshot that drifts from what the CI drift gate regenerates. Any
# module-flag experimentation in your local .env is temporarily shelved.

set -euo pipefail

OUT="docs/openapi/php.json"

mkdir -p "$(dirname "$OUT")"

# Swap .env with .env.example for the duration of the dump so scramble
# sees the shipped-default feature flags.
RESTORE=0
if [ -f .env ]; then
    cp .env .env.dump-openapi.bak
    RESTORE=1
fi
trap '[ "$RESTORE" = "1" ] && mv -f .env.dump-openapi.bak .env || rm -f .env.dump-openapi.bak' EXIT
cp .env.example .env

# Scramble's reflection-based spec generation crosses the default 128M
# limit on larger API surfaces. Raise it explicitly so fresh clones work
# without the developer pre-patching php.ini.
php -d memory_limit=1G artisan scramble:export --path="$OUT"

# Pretty-print + normalise key ordering so diffs stay readable
jq -S '.' "$OUT" > "$OUT.tmp" && mv "$OUT.tmp" "$OUT"

echo "wrote $OUT ($(wc -c < "$OUT") bytes, $(jq '.paths | length' "$OUT") paths)"
