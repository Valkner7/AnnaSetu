<#
Daily mandi price fetch, run from this PC (D3).

The data source (api.data.gov.in) only serves the CURRENT day's prices, so a
day that is not fetched is lost for good, and the GitHub runner cannot always
reach the source. This script does what the "Update mandi prices daily" action
does for the data step: fetch, commit the data files, push. It does NOT touch
any code and it does NOT retrain the model.

It works in its own clone of the price-service repo (default
C:\xampp\htdocs\mandi-fetch), never in the AnnaSetu folder.

Run once by hand:
    powershell -ExecutionPolicy Bypass -File tools\daily-mandi-fetch.ps1

Optional: your own free data.gov.in key. Set it once for your Windows user:
    [Environment]::SetEnvironmentVariable("DATA_GOV_API_KEY", "<your key>", "User")

Schedule it every evening with Task Scheduler (commands are in the chat reply):
prices are usually posted by early evening, so run it late, e.g. 10:30 PM.
A log is kept in %LOCALAPPDATA%\mandi-daily-fetch.log.
#>
param(
    [string]$RepoDir = "C:\xampp\htdocs\mandi-fetch",
    [string]$Python = "python"
)

$ErrorActionPreference = "Stop"
$logFile = Join-Path $env:LOCALAPPDATA "mandi-daily-fetch.log"
function Log($msg) { "$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')  $msg" | Add-Content -Path $logFile }
function Fail($msg) { Log "FAILED: $msg"; Write-Error $msg; exit 1 }

if (-not (Test-Path (Join-Path $RepoDir ".git"))) {
    Fail "No git clone at $RepoDir. Clone https://github.com/Valkner7/mandi-price-forecast.git there first."
}
Set-Location $RepoDir
Log "Start in $RepoDir"

# Start from the latest main so the push is a clean fast-forward.
git pull --rebase 2>&1 | ForEach-Object { Log "git pull: $_" }
if ($LASTEXITCODE -ne 0) { Fail "git pull --rebase failed" }

& $Python fetch_daily_mandi_data.py 2>&1 | ForEach-Object { Log "fetch: $_" }
if ($LASTEXITCODE -ne 0) { Fail "fetch_daily_mandi_data.py failed (exit $LASTEXITCODE). Today's prices were NOT captured." }

# The fetch leaves timestamped backups behind; they must never be committed.
Remove-Item -Path (Join-Path $RepoDir "clean_mandi_prices_backup_*.csv") -ErrorAction SilentlyContinue

git add clean_mandi_prices.csv
if (Test-Path "quarantined_rows.csv") { git add quarantined_rows.csv }

git diff --cached --quiet
if ($LASTEXITCODE -eq 0) {
    Log "No new data to commit (already up to date)."
    exit 0
}

$today = Get-Date -Format "yyyy-MM-dd"
git commit -m "Manual fetch of mandi prices ($today)" 2>&1 | ForEach-Object { Log "git commit: $_" }
if ($LASTEXITCODE -ne 0) { Fail "git commit failed" }

git push origin main 2>&1 | ForEach-Object { Log "git push: $_" }
if ($LASTEXITCODE -ne 0) { Fail "git push failed. The data is committed locally; run 'git push origin main' in $RepoDir." }

Log "Done: data for $today pushed."
