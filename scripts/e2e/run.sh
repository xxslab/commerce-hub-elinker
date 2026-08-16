#!/usr/bin/env bash
#
# Cross-repo real-HTTP E2E handshake between eLinker and License Hub.
#
# Boots a REAL License Hub instance (its own sqlite DB, its own PHP
# built-in server process, its own signed-request verification) on
# localhost, then runs tests/E2E/RealLicenseHubHandshakeTest.php against
# it. That test never calls Http::fake() -- every request eLinker's
# LicenseHubClient makes travels over a real TCP socket to the real Hub
# process this script starts, covering (see the test file for the mapping
# of each scenario to a test method):
#
#   1-6.  connect() consumes a real one-time token, saves the real
#         workspace_id, and the immediate entitlements/check that follows
#         saves active/plan_code/features -- all for real.
#   7.    replaying that same token against a second company is rejected
#         by the real Hub.
#   8.    a request signed with the wrong secret is rejected by the real
#         Hub (401).
#   9.    a real, unreachable-Hub connection failure degrades sync_status
#         only -- entitlement_status/plan_code are untouched.
#   10-11. a real "suspended" status from the Hub (after a refresh) blocks
#         a gated action, while the company's own orders/channels remain
#         both present and readable.
#
# Usage:
#   LICENSE_HUB_REPO=/path/to/license-hub scripts/e2e/run.sh
#
# Requirements on LICENSE_HUB_REPO: a working License Hub checkout with
# `composer install` already run, and its own scripts/e2e_seed.php and
# scripts/e2e_action.php (companion fixtures helpers, committed in that
# repo alongside this one).
#
# The eLinker side needs no separate setup -- the E2E test uses this
# repo's normal RefreshDatabase/:memory: sqlite test database, unrelated
# to License Hub's DB.

set -euo pipefail

ELINKER_REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LICENSE_HUB_REPO="${LICENSE_HUB_REPO:-}"
PORT="${E2E_LICENSE_HUB_PORT:-8091}"

# Must match the constants hardcoded in
# tests/E2E/RealLicenseHubHandshakeTest.php -- this script and that test
# are two halves of one fixture, kept in sync deliberately rather than
# threading the value through yet another env var.
SIGNING_KEY_ID="e2e-key"
SIGNING_SECRET="e2e-secret-do-not-use-in-production-0123456789"

if [[ -z "$LICENSE_HUB_REPO" ]]; then
    echo "LICENSE_HUB_REPO is required -- point it at a license-hub checkout." >&2
    echo "Usage: LICENSE_HUB_REPO=/path/to/license-hub $0" >&2
    exit 2
fi
if [[ ! -f "$LICENSE_HUB_REPO/artisan" ]]; then
    echo "LICENSE_HUB_REPO ($LICENSE_HUB_REPO) doesn't look like a Laravel app (no artisan file)." >&2
    exit 2
fi
if [[ ! -f "$LICENSE_HUB_REPO/scripts/e2e_seed.php" ]]; then
    echo "LICENSE_HUB_REPO is missing scripts/e2e_seed.php -- this needs the License Hub" >&2
    echo "repo's own E2E companion scripts, committed alongside its ProductLinkToken work." >&2
    exit 2
fi

WORKDIR="$(mktemp -d /tmp/elinker-e2e.XXXXXX)"
DB_FILE="$WORKDIR/license-hub.sqlite"
SERVE_LOG="$WORKDIR/license-hub-serve.log"
SERVER_PID=""

cleanup() {
    local exit_code=$?
    if [[ -n "$SERVER_PID" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
    if [[ $exit_code -ne 0 ]]; then
        echo "--- License Hub server log ($SERVE_LOG) ---" >&2
        cat "$SERVE_LOG" >&2 2>/dev/null || true
    fi
    rm -rf "$WORKDIR"
    exit $exit_code
}
trap cleanup EXIT

echo "==> Preparing a fresh License Hub sqlite database at $DB_FILE"
touch "$DB_FILE"
(
    cd "$LICENSE_HUB_REPO"
    DB_CONNECTION=sqlite DB_DATABASE="$DB_FILE" php artisan migrate --force >/dev/null
)

echo "==> Starting License Hub on 127.0.0.1:$PORT"
(
    cd "$LICENSE_HUB_REPO"
    DB_CONNECTION=sqlite \
    DB_DATABASE="$DB_FILE" \
    DOSIECI_SIGNING_KEY_ID="$SIGNING_KEY_ID" \
    DOSIECI_SIGNING_SECRET="$SIGNING_SECRET" \
    LICENSING_RATE_LIMIT_PRODUCT_LINK_CONSUME=1000 \
    php artisan serve --host=127.0.0.1 --port="$PORT" >"$SERVE_LOG" 2>&1 &
    echo $! >"$WORKDIR/server.pid"
)
SERVER_PID="$(cat "$WORKDIR/server.pid")"

echo "==> Waiting for License Hub to accept connections..."
for _ in $(seq 1 30); do
    if curl -s -o /dev/null "http://127.0.0.1:$PORT/admin/login"; then
        break
    fi
    sleep 0.5
done
if ! curl -s -o /dev/null "http://127.0.0.1:$PORT/admin/login"; then
    echo "License Hub did not come up in time." >&2
    exit 1
fi
echo "==> License Hub is up (pid $SERVER_PID)"

echo "==> Running the real cross-repo E2E handshake test"
(
    cd "$ELINKER_REPO"
    E2E_LICENSE_HUB_URL="http://127.0.0.1:$PORT" \
    E2E_LICENSE_HUB_REPO="$LICENSE_HUB_REPO" \
    E2E_LICENSE_HUB_DB_CONNECTION=sqlite \
    E2E_LICENSE_HUB_DB_DATABASE="$DB_FILE" \
    php artisan test tests/E2E/RealLicenseHubHandshakeTest.php
)

echo "==> All real cross-repo E2E scenarios passed."
