# SmartHarvest AI (AnnaSetu)

*From Seed to Sale*

A multilingual (English, Hindi, Punjabi) PHP web app that connects farmers, buyers and equipment owners, and shows crop price forecasts for Punjab mandis. The forecasting service (Mandi Setu) is included in this repo under `ai/mandi_service/`.

> Status: work in progress. The features below exist today; the roadmap lists what does not.

## What works today

- **Accounts and roles:** farmer, buyer, equipment owner, storage provider and transporter sign-up; admin can only be set directly in the database.
- **Farmer tools:** land and crop records, crop planning page, produce listings (with a mandi price hint on the sell form).
- **Marketplaces:** produce marketplace with cart and orders for buyers; equipment fleet and booking for owners and farmers.
- **Market Prices page:** crop and mandi picker, latest price, 7-day forecast, a price-history chart (last 60 reported days plus the projection, drawn with Chart.js) and a data-age notice, served by the price service below.
- **Compare Mandis:** the same crop in several mandis side by side, plus a "use my location" button that lists the nearest mandis.
- **Crop planning:** recommendations come from real price comparison (the older simulated values are gone).
- **Ask Mandi Setu (`?url=advisory`):** type a question or speak it (browser speech recognition), for one mandi or a comparison. The answer can be read aloud as MP3. Needs `GEMINI_API_KEY` for natural wording; without it a plain template answer is given. Each user is limited to 12 questions per 10 minutes, and the key stays on the server.
- **Price alerts (`?url=alerts`):** set a price above/below a level for a crop and mandi. A check compares the newest reported price with the alert, marks it "Reached" and shows it on the page and the dashboard. Alerts are in-app only (no SMS or WhatsApp). Stored in MySQL (`price_alerts`).
- **Dashboard card:** a "Market Prices" card with links, the number of reached/active alerts and a health badge for the price service.
- **Languages:** English, Hindi and Punjabi (`lang/en.php`, `lang/hi.php`, `lang/pa.php`), switched from the page header. On Market Prices everything is shown in the chosen language: labels, crop names (e.g. आलू (Potato)), the trend word, the confidence note and the data-age notices. The price service itself writes English, so AnnaSetu translates those texts from the dates and numbers it sends (`app/Helpers/MandiText.php`). The Hindi and Punjabi wording has not been reviewed by a native speaker yet.
- **Security basics:** session CSRF token on every POST, escaped view output, validated and re-encoded image uploads, login throttling, hardened session cookies and security headers.

## Roadmap (not built yet)

Storage marketplace, logistics, IoT monitoring and AI crop grading.

## Technology

- **App:** PHP 8.2+ (PDO, cURL), MySQL/MariaDB, Apache (developed on XAMPP), Bootstrap 5, plain JavaScript, Chart.js.
- **Price service (Mandi Setu):** a Python/FastAPI app in `ai/mandi_service/` (see `ai/mandi_service/README.md`). It runs as its own process, because the PHP app cannot run the forecast model. AnnaSetu calls it server-side and caches responses in `storage/`, serving stale data if the service is down. AnnaSetu tries the bundled copy on this computer first (`http://127.0.0.1:8001`) and falls back to the copy hosted on Render if it is not running; see "Run the price service locally" below.

## Folder structure

- `app/Controllers`, `app/Models`, `app/Services`, `app/Helpers`, `app/Core`: application code (folder names are capitalised to match the namespaces, which matters on Linux).
- `views/`: PHP templates.
- `lang/`: translation files.
- `config/`: settings. `database.php` and `mandi.local.php` are git-ignored; copy the `*.example.php` files.
- `database/`: SQL schema files (`schema_phase1.sql` to `schema_phase7.sql`).
- `public/`: web root (`index.php`, assets, `uploads/`).
- `storage/`: cache and runtime files (git-ignored).
- `ai/mandi_service/`: the Mandi Setu price service (a snapshot copy; see `ai/mandi_service/UPSTREAM.md`).
- `tools/`: helper scripts (`linux/` has the cron/systemd versions of the Windows jobs; `sync-mandi-service.ps1` refreshes the copy of the price service; `start-mandi-service.ps1`, `mandi-daily-job.ps1` and `register-mandi-tasks.ps1` run and schedule it; `check-price-alerts.php` checks alerts).
- `tests/`: offline checks (no database or network needed).

## Setup

1. Copy `config/database.example.php` to `config/database.php` and fill in your MySQL details.
2. Run `php setup_db.php` from the project folder. It creates the `smartharvest_db` database and applies every schema file in `database/` (phases 1 to 7) in order, including the farmer, buyer, equipment, storage and price-alert tables, and adds the Punjabi crop-name column (`crops.name_pa`) that the buyer marketplace needs. It is safe to re-run after pulling new code. `run_schema4.php`, `run_schema5.php` and `run_schema7.php` still work but are no longer needed.
3. Optional: copy `config/mandi.local.example.php` to `config/mandi.local.php` to change the price service address or send a shared secret.
4. Serve `public/` with Apache (e.g. XAMPP) and open it in a browser.

### Run the price service locally

AnnaSetu calls the bundled price service at `http://127.0.0.1:8001` first and uses the hosted Render copy only if the local one is unreachable (or returns a server error). To start the local one (Windows; needs Python 3.12 or newer, found automatically, the `py` launcher works too):

```powershell
powershell -ExecutionPolicy Bypass -File tools\start-mandi-service.ps1
```

The first run creates `ai\mandi_service\.venv` and installs the libraries (a few minutes). Options: `-Port`, `-Install` (reinstall libraries), `-Hidden` (no window, log in `storage\logs\`). If you change the port, set `'api_base'` in `config/mandi.local.php` to match. To turn the hosted fallback off, set `'fallback_base' => ''` there.

Optional settings for the service (Gemini key for the advisory page, shared secret) go in `config\mandi-service.env`; copy `config\mandi-service.env.example` (the copy is git-ignored). The price and forecast pages do not need a key.

The bundled copy has the data and model from when it was copied or last refreshed by the daily job. Refresh it deliberately with `tools\sync-mandi-service.ps1`.

### Schedule the daily fetch and the alert check (Windows)

The price source only serves the current day, so a day that is not fetched is lost. Three Windows scheduled tasks (for your user, no administrator needed) take care of it:

```powershell
powershell -ExecutionPolicy Bypass -File tools\register-mandi-tasks.ps1
```

- **Price service** starts when you log in (`tools\start-mandi-service.ps1`).
- **Daily fetch** at 22:30 (or when the PC is next on): `tools\mandi-daily-job.ps1` fetches in your own clone of the price repo (default `C:\xampp\htdocs\mandi-fetch`, see `tools\daily-mandi-fetch.ps1`), then copies the new CSV files into `ai\mandi_service` and clears the cache. Add `-Retrain` to the register command to retrain the model as well.
- **Price alerts** are checked every 30 minutes with `tools\check-price-alerts.php` (needs MySQL running).

Logs are in `storage\logs\`. Remove the tasks with `tools\register-mandi-tasks.ps1 -Remove`.

### Run and schedule on Linux (cron)

Everything above also exists for Linux in `tools/linux/` (needs Python 3.12+, `git`, `curl`, `flock`):

```bash
bash tools/linux/start-mandi-service.sh            # start the price service (creates .venv on first run)
bash tools/linux/mandi-daily-job.sh --init         # one time: clone the price repo to ~/mandi-fetch (or set MANDI_REPO)
bash tools/linux/mandi-daily-job.sh                # fetch today's prices, copy the CSVs in, clear the cache
bash tools/linux/mandi-daily-job.sh --retrain      # same, then retrain the forecast model
```

- `tools/linux/crontab.example`: the daily fetch (22:30) and the alert check (every 30 minutes). Paste it with `crontab -e` and fix the install path.
- `tools/linux/mandi-service.service.example`: a systemd unit that keeps the price service running and starts it at boot.
- Install the price repo's libraries once before the first fetch: `python3 -m pip install -r ~/mandi-fetch/requirements.txt`.
- On a server that stays on, a missed day is no longer lost because your own PC was off. If `api.data.gov.in` cannot be reached from the server either, the fetch fails loudly in `storage/logs/mandi-daily-job.log` and the old data keeps being used.

### About the price service's own alert endpoints

The bundled service still contains its own `/check-alerts` and WhatsApp/SMS subscription code from the original Mandi Setu project. AnnaSetu does **not** use them (that part is parked for now). AnnaSetu's price alerts are a separate system: they live in MySQL (`price_alerts`) and are checked by `tools/check-price-alerts.php`, which is the only alert check you need to schedule.

The daily job overwrites the tracked CSV (and, with `-Retrain`, the model) in `ai\mandi_service`, so `git status` shows them as modified. Do not commit that every day; use `tools\sync-mandi-service.ps1` when you want the repository copy refreshed on purpose.

### Settings

| Setting | Where | Purpose |
|---|---|---|
| `APP_BASE_URL` | environment variable | Overrides the detected base path (default is derived from the script path, so the app works in any folder). |
| `MANDI_API_BASE` | environment variable or `api_base` in `mandi.local.php` | Address of the price service (default `http://127.0.0.1:8001`). |
| `fallback_base` | `mandi.local.php` | Hosted copy used when the local service is down (default Render; `''` turns it off). |
| `MANDI_API_SECRET` | environment variable or `api_secret` in `mandi.local.php` | Shared secret sent to the advisory endpoints; must match `API_SHARED_SECRET` on the service. Never commit it. |

## Tests

```
for t in tests/check_*.php; do php "$t"; done
node tests/check_price_hint.js
```

GitHub Actions (`.github/workflows/ci.yml`) lints every PHP file and runs these checks on Linux for each push and pull request. A second job installs the price service and runs its tests (`ai/mandi_service/test_*.py`).
