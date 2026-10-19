<#
Daily price job for this PC (run by the scheduled task, or by hand).

1. Runs tools\daily-mandi-fetch.ps1: it pulls, fetches today's prices, commits and
   pushes in your own clone of the price-service repo (default C:\xampp\htdocs\mandi-fetch).
2. Only if that worked: copies clean_mandi_prices.csv and quarantined_rows.csv from
   that clone into ai\mandi_service, so the local price service and AnnaSetu see the
   new prices. The running service notices the new files by itself (no restart).
3. Empties the AnnaSetu price cache (storage\cache\mandi) so old answers are not shown.
4. With -Retrain: retrains the forecast model from the new data (a few minutes).

If the fetch fails, nothing is copied and the old data keeps being used.

NOTE: the copied CSV (and model) inside ai\mandi_service are tracked by git, so
"git status" will show them as modified after every run. Do NOT commit that every
day. When you want the AnnaSetu repo itself refreshed, use tools\sync-mandi-service.ps1.

Run by hand:
    powershell -ExecutionPolicy Bypass -File tools\mandi-daily-job.ps1
Log: storage\logs\mandi-daily-job.log
#>
param(
    [string]$MandiRepo = "C:\xampp\htdocs\mandi-fetch",
    [string]$Python = "python",
    [switch]$Retrain
)
$ErrorActionPreference = "Stop"

$root   = Split-Path -Parent $PSScriptRoot
$svcDir = Join-Path (Join-Path $root "ai") "mandi_service"
$logDir = Join-Path (Join-Path $root "storage") "logs"
$cache  = Join-Path (Join-Path (Join-Path $root "storage") "cache") "mandi"
$logFile = Join-Path $logDir "mandi-daily-job.log"
New-Item -ItemType Directory -Path $logDir -Force | Out-Null

function Log([string]$msg) {
    $msg = ([string]$msg) -replace "\x1b\[[0-9;]*m", ""      # no colour codes in the log file
    $line = "{0}  {1}" -f (Get-Date -Format "yyyy-MM-dd HH:mm:ss"), $msg
    Add-Content -Path $logFile -Value $line
    Write-Host $line
}

function Copy-Data([string]$name, [bool]$required) {
    $src = Join-Path $MandiRepo $name
    $dst = Join-Path $svcDir $name
    if (-not (Test-Path $src)) {
        if ($required) { throw "$name was not found in $MandiRepo after the fetch." }
        Log "$name not in the mandi clone, skipped."
        return
    }
    if ((Get-Item $src).Length -lt 20) { throw "$name in $MandiRepo is empty. Not copying." }
    # The header line must match the current file, so a damaged file never replaces good data.
    if (Test-Path $dst) {
        $h1 = (Get-Content -Path $src -TotalCount 1).Trim().TrimStart([char]0xFEFF)
        $h2 = (Get-Content -Path $dst -TotalCount 1).Trim().TrimStart([char]0xFEFF)
        if ($h1 -ne $h2) { throw "$name has a different header than the current copy. Not copying." }
    }
    $tmp = "$dst.new"
    Copy-Item -Path $src -Destination $tmp -Force
    Move-Item -Path $tmp -Destination $dst -Force
    Log ("Copied {0} ({1} KB)" -f $name, [math]::Round((Get-Item $dst).Length / 1KB))
}

try {
    Log "Daily job started (mandi clone: $MandiRepo)"
    if (-not (Test-Path $svcDir)) { throw "ai\mandi_service was not found. Run this from the AnnaSetu folder." }

    # 1. Fetch (own clone, never this folder). Same PowerShell program that is running this script.
    $fetchScript = Join-Path $PSScriptRoot "daily-mandi-fetch.ps1"
    if (-not (Test-Path $fetchScript)) { throw "tools\daily-mandi-fetch.ps1 was not found." }
    $host_ = (Get-Process -Id $PID).Path
    # Windows PowerShell 5.1 treats any text a program writes to stderr as an error when
    # ErrorActionPreference is Stop, so relax it while an external program runs.
    $ErrorActionPreference = "Continue"
    & $host_ -NoProfile -ExecutionPolicy Bypass -File $fetchScript -RepoDir $MandiRepo -Python $Python 2>&1 |
        ForEach-Object { Log "fetch: $_" }
    $fetchExit = $LASTEXITCODE
    $ErrorActionPreference = "Stop"
    if ($fetchExit -ne 0) {
        throw "The fetch failed (exit $fetchExit). Nothing was copied; see %LOCALAPPDATA%\mandi-daily-fetch.log."
    }

    # 2. Copy the data into the bundled service
    Copy-Data "clean_mandi_prices.csv" $true
    Copy-Data "quarantined_rows.csv" $false

    # 3. Forget old cached answers
    if (Test-Path $cache) {
        $old = @(Get-ChildItem -Path $cache -Filter "*.json" -File -ErrorAction SilentlyContinue)
        $old | Remove-Item -Force -ErrorAction SilentlyContinue
        Log ("Cleared {0} cached answer(s)" -f $old.Count)
    }

    # 4. Optional retrain with the service's own Python environment
    if ($Retrain) {
        $venvPy = Join-Path (Join-Path (Join-Path $svcDir ".venv") "Scripts") "python.exe"
        if (-not (Test-Path $venvPy)) { throw "Retrain needs the price service environment. Run tools\start-mandi-service.ps1 once first." }
        Log "Retraining the forecast model ..."
        Push-Location $svcDir
        try {
            $ErrorActionPreference = "Continue"
            & $venvPy "train_forecast_model.py" 2>&1 | ForEach-Object { Log "train: $_" }
            $trainExit = $LASTEXITCODE
            $ErrorActionPreference = "Stop"
            if ($trainExit -ne 0) { throw "Retraining failed (exit $trainExit). The old model is still in use." }
        } finally { Pop-Location }
    }

    Log "Daily job finished OK."
    exit 0
} catch {
    Log ("FAILED: " + $_.Exception.Message)
    exit 1
}
