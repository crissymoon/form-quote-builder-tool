#!/usr/bin/env bash
# dev.sh
# Starts the development environment.
# If devtool/target/release/xcm-dev is built it is used directly.
# Otherwise the plain bash fallback runs setup, syncs README, and starts PHP.
#
# Usage: bash dev.sh [port]

set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
BINARY="${ROOT}/devtool/target/release/xcm-dev"

# ---- Use compiled Rust binary if available ----
if [[ -f "${BINARY}" ]]; then
    exec "${BINARY}" --root "${ROOT}"
fi

# ---- Bash fallback ----
HOST="127.0.0.1"
PORT="${1:-8080}"

NEEDS_SETUP=0
[[ ! -d "${ROOT}/vendor" ]]                            && NEEDS_SETUP=1
[[ ! -f "${ROOT}/assets/js/vendor/math.min.js" ]]     && NEEDS_SETUP=1

if [[ "$NEEDS_SETUP" -eq 1 ]]; then
    bash "${ROOT}/setup.sh"
    echo ""
fi

echo "Syncing README from project notes..."
php "${ROOT}/project_mgr/sync_readme.php"

echo ""
echo "---"
echo "  Dashboard:       http://${HOST}:${PORT}/dashboard"
echo "  Project Manager: http://${HOST}:${PORT}/project-mgr"
echo "  Form Builder:    http://${HOST}:${PORT}/"
echo "  Form Example:    http://${HOST}:${PORT}/?demo=1"
echo "  Add a note:      php project_mgr/add_note.php --user \"Name\" --title \"Title\" --body \"Text\""
echo "  Sync README:     php project_mgr/sync_readme.php"
echo "  Build Rust tool: cd devtool && cargo build --release"
echo "---"
echo "Starting server... (Ctrl+C to stop)"
echo ""

cd "${ROOT}"
php -S "${HOST}:${PORT}" router.php &
SERVER_PID=$!

for i in {1..20}; do
    if curl -s --max-time 1 "http://${HOST}:${PORT}/" -o /dev/null 2>/dev/null; then
        break
    fi
    sleep 0.1
done

BASE="http://${HOST}:${PORT}"
if command -v open &>/dev/null; then
    open "${BASE}/dashboard"
    sleep 0.3
    open "${BASE}/project-mgr"
    sleep 0.3
    open "${BASE}/"
    sleep 0.3
    open "${BASE}/?demo=1"
elif command -v xdg-open &>/dev/null; then
    xdg-open "${BASE}/dashboard" &
    sleep 0.3
    xdg-open "${BASE}/project-mgr" &
    sleep 0.3
    xdg-open "${BASE}/" &
    sleep 0.3
    xdg-open "${BASE}/?demo=1" &
fi

wait "${SERVER_PID}"
