#!/usr/bin/env bash
# Daily price job for Linux (cron). Linux equivalent of tools/daily-mandi-fetch.ps1
# plus tools/mandi-daily-job.ps1.
#
#   1. In your own clone of the price repo ($MANDI_REPO): git pull, fetch today's
#      prices, commit and push the data files (skip with --no-push).
#   2. Only if that worked: copy clean_mandi_prices.csv and quarantined_rows.csv into
#      ai/mandi_service (a damaged or empty file never replaces good data).
#   3. Empty the AnnaSetu price cache (storage/cache/mandi).
#   4. With --retrain: retrain the forecast model with the service's own venv.
#
# The data source only serves the CURRENT day, so a day that is not fetched is lost.
# Run it late in the evening (prices are usually posted by early evening).
#
# Settings (environment variables, or put them in config/mandi-service.env):
#   MANDI_REPO       clone of https://github.com/Valkner7/mandi-price-forecast.git
#                    (default: $HOME/mandi-fetch; created by --init)
#   DATA_GOV_API_KEY optional own data.gov.in key
#   PYTHON           python used for the fetch (default python3)
#
#   bash tools/linux/mandi-daily-job.sh --init     # one-time: clone the price repo
#   bash tools/linux/mandi-daily-job.sh            # fetch + copy + clear cache
#   bash tools/linux/mandi-daily-job.sh --retrain --no-push
# Log: storage/logs/mandi-daily-job.log
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SVC="$ROOT/ai/mandi_service"
LOG_DIR="$ROOT/storage/logs"
CACHE="$ROOT/storage/cache/mandi"
LOG="$LOG_DIR/mandi-daily-job.log"
mkdir -p "$LOG_DIR"

ENV_FILE="$ROOT/config/mandi-service.env"
if [ -f "$ENV_FILE" ]; then set -a; . "$ENV_FILE"; set +a; fi

MANDI_REPO="${MANDI_REPO:-$HOME/mandi-fetch}"
PYTHON="${PYTHON:-python3}"
SOURCE_URL="https://github.com/Valkner7/mandi-price-forecast.git"
RETRAIN=0; PUSH=1; INIT=0
for a in "$@"; do
    case "$a" in
        --retrain) RETRAIN=1 ;;
        --no-push) PUSH=0 ;;
        --init)    INIT=1 ;;
        *) echo "Unknown option: $a" >&2; exit 2 ;;
    esac
done

log() { printf '%s  %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" | tee -a "$LOG"; }
fail() { log "FAILED: $*"; exit 1; }

if [ "$INIT" = 1 ]; then
    [ -d "$MANDI_REPO/.git" ] && { echo "Already cloned at $MANDI_REPO"; exit 0; }
    git clone "$SOURCE_URL" "$MANDI_REPO" || exit 1
    echo "Cloned to $MANDI_REPO. Install its libraries once: $PYTHON -m pip install -r $MANDI_REPO/requirements.txt"
    exit 0
fi

# Never run two jobs at once.
exec 9>"$LOG_DIR/mandi-daily-job.lock"
flock -n 9 || { echo "Another daily job is running."; exit 0; }

log "Daily job started (mandi clone: $MANDI_REPO)"
[ -d "$SVC" ] || fail "ai/mandi_service not found."
[ -d "$MANDI_REPO/.git" ] || fail "No git clone at $MANDI_REPO. Run: bash tools/linux/mandi-daily-job.sh --init"

# 1. Fetch in the separate clone (never in this folder).
(
    set -e
    cd "$MANDI_REPO"
    git pull --rebase 2>&1 | sed 's/^/pull: /'
    "$PYTHON" fetch_daily_mandi_data.py 2>&1 | sed 's/^/fetch: /'
    rm -f clean_mandi_prices_backup_*.csv
    if [ "$PUSH" = 1 ]; then
        git add clean_mandi_prices.csv
        [ -f quarantined_rows.csv ] && git add quarantined_rows.csv
        if git diff --cached --quiet; then
            echo "commit: no new data to commit."
        else
            git commit -m "Manual fetch of mandi prices ($(date +%F))" 2>&1 | sed 's/^/commit: /'
            git push origin main 2>&1 | sed 's/^/push: /'
        fi
    fi
) 2>&1 | while IFS= read -r line; do log "$line"; done
[ "${PIPESTATUS[0]}" -eq 0 ] || fail "The fetch failed. Nothing was copied; the old data stays in use."

# 2. Copy data into the bundled service, with safety checks.
copy_data() {
    local name="$1" required="$2" src="$MANDI_REPO/$1" dst="$SVC/$1"
    if [ ! -f "$src" ]; then
        [ "$required" = 1 ] && fail "$name was not found in $MANDI_REPO after the fetch."
        log "$name not in the mandi clone, skipped."; return 0
    fi
    [ "$(stat -c %s "$src")" -ge 20 ] || fail "$name in $MANDI_REPO is empty. Not copying."
    if [ -f "$dst" ]; then
        h1="$(head -n1 "$src" | tr -d '\r\357\273\277')"
        h2="$(head -n1 "$dst" | tr -d '\r\357\273\277')"
        [ "$h1" = "$h2" ] || fail "$name has a different header than the current copy. Not copying."
    fi
    cp "$src" "$dst.new" && mv -f "$dst.new" "$dst" || fail "could not copy $name"
    log "Copied $name ($(( $(stat -c %s "$dst") / 1024 )) KB)"
}
copy_data clean_mandi_prices.csv 1
copy_data quarantined_rows.csv 0

# 3. Forget old cached answers.
if [ -d "$CACHE" ]; then
    n=$(find "$CACHE" -maxdepth 1 -name '*.json' -type f | wc -l)
    find "$CACHE" -maxdepth 1 -name '*.json' -type f -delete
    log "Cleared $n cached answer(s)"
fi

# 4. Optional retrain.
if [ "$RETRAIN" = 1 ]; then
    [ -x "$SVC/.venv/bin/python" ] || fail "Retrain needs the service environment. Run tools/linux/start-mandi-service.sh once first."
    log "Retraining the forecast model ..."
    rc=0
    ( cd "$SVC" && .venv/bin/python train_forecast_model.py 2>&1 | while IFS= read -r l; do log "train: $l"; done; exit "${PIPESTATUS[0]}" ) || rc=$?
    if [ "$rc" = 3 ]; then
        # Exit code 3 = the trainer's quality gate: the new model did not beat "price stays the
        # same", so it was not saved. That is the gate working, not a failure of the job.
        log "WARNING: the new model did not beat 'price stays the same', so it was NOT saved. The old model stays in use."
    elif [ "$rc" != 0 ]; then
        fail "Retraining failed (exit $rc). The old model is still in use."
    fi
fi

log "Daily job finished OK."
