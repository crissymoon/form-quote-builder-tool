#!/usr/bin/env bash
# dev.sh
# Runs the full development environment:
#   1. Ensures all dependencies are installed (setup.sh)
#   2. Syncs README.md with the latest project_mgr notes
#   3. Starts the PHP built-in development server
#
# Usage: bash dev.sh [port]
# Default port: 8080

set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
HOST="127.0.0.1"
PORT="${1:-8080}"

# ----- Dependency check -----
NEEDS_SETUP=0
[[ ! -d "${ROOT}/vendor" ]]                            && NEEDS_SETUP=1
[[ ! -f "${ROOT}/assets/js/vendor/math.min.js" ]]     && NEEDS_SETUP=1

if [[ "$NEEDS_SETUP" -eq 1 ]]; then
    bash "${ROOT}/setup.sh"
    echo ""
fi

# ----- Sync README from project notes -----
echo "Syncing README from project notes..."
php "${ROOT}/project_mgr/sync_readme.php"

echo ""
echo "---"
echo "  Project Manager: http://${HOST}:${PORT}/project-mgr"
echo "  Form Builder:    http://${HOST}:${PORT}/"
echo "  Form Example:    http://${HOST}:${PORT}/?demo=1"
echo "  Add a note:      php project_mgr/add_note.php --user \"Name\" --title \"Title\" --body \"Text\""
echo "  Sync README:     php project_mgr/sync_readme.php"
echo "  Re-run setup:    bash setup.sh"
echo "---"
echo "Starting server... (Ctrl+C to stop)"
echo ""

cd "${ROOT}"
php -S "${HOST}:${PORT}" router.php &
SERVER_PID=$!

# Wait for the server to be ready before opening the browser
for i in {1..20}; do
    if curl -s --max-time 1 "http://${HOST}:${PORT}/" -o /dev/null 2>/dev/null; then
        break
    fi
    sleep 0.1
done

# Open all 3 views in the browser (macOS: open, Linux: xdg-open)
BASE="http://${HOST}:${PORT}"
if command -v open &>/dev/null; then
    open "${BASE}/project-mgr"
    sleep 0.3
    open "${BASE}/"
    sleep 0.3
    open "${BASE}/?demo=1"
elif command -v xdg-open &>/dev/null; then
    xdg-open "${BASE}/project-mgr" &
    sleep 0.3
    xdg-open "${BASE}/" &
    sleep 0.3
    xdg-open "${BASE}/?demo=1" &
fi

# Wait for the server process so Ctrl+C works cleanly
wait "${SERVER_PID}"
