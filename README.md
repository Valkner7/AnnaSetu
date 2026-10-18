# SmartHarvest AI (AnnaSetu)

*From Seed to Sale*

A multilingual (English, Hindi, Punjabi) PHP web app that connects farmers, buyers and equipment owners, and shows crop price forecasts for Punjab mandis. The forecasting service (Mandi Setu) is included in this repo under `ai/mandi_service/`.

> Status: work in progress. The features below exist today; the roadmap lists what does not.

## What works today

- **Accounts and roles:** farmer, buyer, equipment owner, storage provider and transporter sign-up; admin can only be set directly in the database.
- **Farmer tools:** land and crop records, crop planning page, produce listings (with a mandi price hint on the sell form).
- **Marketplaces:** produce marketplace with cart and orders for buyers; equipment fleet and booking for owners and farmers.
- **Market Prices page:** crop and mandi picker, latest price, 7-day forecast, a price-history chart (last 60 reported days plus the projection, drawn with Chart.js) and a data-age notice, served by the price service below.
- **Languages:** English, Hindi and Punjabi (`lang/en.php`, `lang/hi.php`, `lang/pa.php`), switched from the page header. On Market Prices everything is shown in the chosen language: labels, crop names (e.g. आलू (Potato)), the trend word, the confidence note and the data-age notices. The price service itself writes English, so AnnaSetu translates those texts from the dates and numbers it sends (`app/Helpers/MandiText.php`). The Hindi and Punjabi wording has not been reviewed by a native speaker yet.
- **Security basics:** session CSRF token on every POST, escaped view output, validated and re-encoded image uploads, login throttling, hardened session cookies and security headers.

## Roadmap (not built yet)

Real crop-planning recommendations from price history, nearby and compare mandis, voice/text advisory, price alerts, storage marketplace, logistics, IoT monitoring and AI crop grading.

## Technology

- **App:** PHP 8.2+ (PDO, cURL), MySQL/MariaDB, Apache (developed on XAMPP), Bootstrap 5, plain JavaScript, Chart.js.
- **Price service (Mandi Setu):** a Python/FastAPI app in `ai/mandi_service/` (see `ai/mandi_service/README.md`). It runs as its own process, because the PHP app cannot run the forecast model. AnnaSetu calls it server-side and caches responses in `storage/`, serving stale data if the service is down. By default it uses the copy hosted on Render; to use the bundled copy, see "Run the price service locally" below.

## Folder structure

- `app/Controllers`, `app/Models`, `app/Services`, `app/Helpers`, `app/Core`: application code (folder names are capitalised to match the namespaces, which matters on Linux).
- `views/`: PHP templates.
- `lang/`: translation files.
- `config/`: settings. `database.php` and `mandi.local.php` are git-ignored; copy the `*.example.php` files.
- `database/`: SQL schema files (`schema_phase1.sql` to `schema_phase6.sql`).
- `public/`: web root (`index.php`, assets, `uploads/`).
- `storage/`: cache and runtime files (git-ignored).
- `ai/mandi_service/`: the Mandi Setu price service (a snapshot copy; see `ai/mandi_service/UPSTREAM.md`).
- `tools/`: helper scripts (`sync-mandi-service.ps1` refreshes the copy of the price service).
- `tests/`: offline checks (no database or network needed).

## Setup

1. Copy `config/database.example.php` to `config/database.php` and fill in your MySQL details.
2. Create the database and run `setup_db.php`, then the schema files in `database/` in order if you need them separately.
3. Optional: copy `config/mandi.local.example.php` to `config/mandi.local.php` to change the price service address or send a shared secret.
4. Serve `public/` with Apache (e.g. XAMPP) and open it in a browser.

### Run the price service locally (optional)

By default AnnaSetu uses the hosted service. To run the bundled copy on your own machine instead:

```
cd ai\mandi_service
pip install -r requirements.txt
python -m uvicorn app:app --port 8000
```

This needs Python 3.12 or newer (the service's libraries are pinned for 3.14). Then set `'api_base' => 'http://127.0.0.1:8000'` in `config/mandi.local.php` (plain http is only accepted for localhost). The advisory endpoints (Gemini) need `GEMINI_API_KEY`; the price and forecast pages do not.

The bundled copy has the price data and model as they were when it was copied. The source repo fetches prices every day and retrains, so refresh the copy with `tools\sync-mandi-service.ps1` to pick that up.

### Settings

| Setting | Where | Purpose |
|---|---|---|
| `APP_BASE_URL` | environment variable | Overrides the detected base path (default is derived from the script path, so the app works in any folder). |
| `MANDI_API_BASE` | environment variable or `api_base` in `mandi.local.php` | Address of the price service (hosted or local). |
| `MANDI_API_SECRET` | environment variable or `api_secret` in `mandi.local.php` | Shared secret sent to the advisory endpoints; must match `API_SHARED_SECRET` on the service. Never commit it. |

## Tests

```
for t in tests/check_*.php; do php "$t"; done
node tests/check_price_hint.js
```

GitHub Actions (`.github/workflows/ci.yml`) lints every PHP file and runs these checks on Linux for each push and pull request. A second job installs the price service and runs its tests (`ai/mandi_service/test_*.py`).
