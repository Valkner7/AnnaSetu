#!/usr/bin/env bash
# Starts the bundled Mandi Setu price service (ai/mandi_service) on Linux/macOS.
# Linux equivalent of tools/start-mandi-service.ps1.
#
#   bash tools/linux/start-mandi-service.sh            # foreground (Ctrl+C to stop)
#   bash tools/linux/start-mandi-service.sh --install  # reinstall libraries first
#   PORT=8002 bash tools/linux/start-mandi-service.sh  # other port (then set 'api_base' in config/mandi.local.php)
#
# Needs Python 3.12 or newer. The first run creates ai/mandi_service/.venv.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SVC="$ROOT/ai/mandi_service"
VENV="$SVC/.venv"
PORT="${PORT:-8001}"
PYTHON="${PYTHON:-python3}"
INSTALL=0
[ "${1:-}" = "--install" ] && INSTALL=1

if curl -fsS --max-time 5 "http://127.0.0.1:$PORT/status" >/dev/null 2>&1; then
    echo "The price service is already running on port $PORT."
    exit 0
fi

[ -f "$SVC/app.py" ] || { echo "ai/mandi_service/app.py not found." >&2; exit 1; }

if [ ! -x "$VENV/bin/python" ]; then
    "$PYTHON" -c 'import sys; sys.exit(0 if sys.version_info >= (3, 12) else 1)' \
        || { echo "Python 3.12 or newer is required (found: $("$PYTHON" --version 2>&1))." >&2; exit 1; }
    echo "Creating virtual environment ..."
    "$PYTHON" -m venv "$VENV"
    INSTALL=1
fi
if [ "$INSTALL" = 1 ]; then
    echo "Installing libraries (this can take a few minutes) ..."
    "$VENV/bin/python" -m pip install --upgrade pip --quiet
    "$VENV/bin/python" -m pip install -r "$SVC/requirements-slim.txt"
fi

# Slim mode (the default here): only the endpoints AnnaSetu uses. The original project's WhatsApp/SMS
# bot, /check-alerts, Turso and the React dashboard are switched off and their libraries are not
# needed. Set MANDI_SLIM=0 in config/mandi-service.env to run everything (then install
# ai/mandi_service/requirements.txt yourself).
export MANDI_SLIM="${MANDI_SLIM:-1}"

# Optional settings file: KEY=VALUE per line, # for comments.
ENV_FILE="$ROOT/config/mandi-service.env"
if [ -f "$ENV_FILE" ]; then
    set -a
    # shellcheck disable=SC1090
    . "$ENV_FILE"
    set +a
    echo "Loaded settings from config/mandi-service.env"
fi

cd "$SVC"
echo "Starting the price service on http://127.0.0.1:$PORT"
exec "$VENV/bin/python" -m uvicorn app:app --host 127.0.0.1 --port "$PORT"
