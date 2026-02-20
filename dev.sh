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
echo "  App:            http://${HOST}:${PORT}"
echo "  Add a note:     php project_mgr/add_note.php --user \"Name\" --title \"Title\" --body \"Text\""
echo "  Sync README:    php project_mgr/sync_readme.php"
echo "  Re-run setup:   bash setup.sh"
echo "---"
echo "Starting server... (Ctrl+C to stop)"
echo ""

cd "${ROOT}"
exec php -S "${HOST}:${PORT}" router.php
