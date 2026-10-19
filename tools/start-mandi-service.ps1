<#
Starts the bundled Mandi Setu price service (ai\mandi_service) on this computer.

AnnaSetu calls it at http://127.0.0.1:8001 first and only uses the hosted copy
on Render if this one is not running, so you get fast answers with no cold start.

First run (creates ai\mandi_service\.venv and installs the libraries, a few minutes):
    powershell -ExecutionPolicy Bypass -File tools\start-mandi-service.ps1

Needs Python 3.12 or newer on PATH (python --version).

Options:
    -Port 8001   Port to listen on. If you change it, set 'api_base' in config\mandi.local.php to match.
    -Install     Reinstall the libraries (use after the service's requirements.txt changes).
    -Hidden      Run with no window and write a log to storage\logs\mandi-service.log
                 (this is what the scheduled task uses).

Optional settings (Gemini key for the advisory page, shared secret) go in
config\mandi-service.env, one KEY=VALUE per line. See config\mandi-service.env.example.
The file is git-ignored.
#>
param(
    [int]$Port = 8001,
    [switch]$Install,
    [switch]$Hidden
)
$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$svc  = Join-Path $root "ai\mandi_service"
$venv = Join-Path $svc ".venv"
$py   = Join-Path $venv "Scripts\python.exe"
$logDir = Join-Path $root "storage\logs"

function Test-Service([int]$p) {
    try {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:$p/status" -UseBasicParsing -TimeoutSec 5
        return ($r.StatusCode -eq 200)
    } catch { return $false }
}

if (Test-Service $Port) {
    Write-Host "The price service is already running on port $Port."
    exit 0
}

if (-not (Test-Path (Join-Path $svc "app.py"))) { throw "ai\mandi_service\app.py not found. Run this from the AnnaSetu folder." }

# 1. Virtual environment and libraries
if (-not (Test-Path $py)) {
    $ver = $null
    try { $ver = & python -c "import sys; print('%d.%d' % sys.version_info[:2])" 2>$null } catch { }
    if (-not $ver) { throw "Python was not found. Install Python 3.12 or newer and tick 'Add python.exe to PATH'." }
    $parts = $ver.Split(".")
    if ([int]$parts[0] -lt 3 -or ([int]$parts[0] -eq 3 -and [int]$parts[1] -lt 12)) { throw "Python $ver is too old. Install Python 3.12 or newer." }
    Write-Host "Creating virtual environment with Python $ver ..."
    & python -m venv $venv
    if ($LASTEXITCODE -ne 0) { throw "Could not create the virtual environment." }
    $Install = $true
}
if ($Install) {
    Write-Host "Installing libraries (this can take a few minutes) ..."
    & $py -m pip install --upgrade pip --quiet
    & $py -m pip install -r (Join-Path $svc "requirements.txt")
    if ($LASTEXITCODE -ne 0) { throw "pip install failed. Check your internet connection and try again." }
}

# 2. Optional settings file (KEY=VALUE per line; lines starting with # are ignored)
$envFile = Join-Path $root "config\mandi-service.env"
if (Test-Path $envFile) {
    foreach ($line in Get-Content $envFile) {
        $l = $line.Trim()
        if ($l -eq "" -or $l.StartsWith("#")) { continue }
        $i = $l.IndexOf("=")
        if ($i -lt 1) { continue }
        $name = $l.Substring(0, $i).Trim()
        $val  = $l.Substring($i + 1).Trim().Trim('"').Trim("'")
        if ($name -match '^[A-Za-z_][A-Za-z0-9_]*$') { Set-Item -Path ("Env:" + $name) -Value $val }
    }
    Write-Host "Loaded settings from config\mandi-service.env"
}

# 3. Start
$uvArgs = @("-m", "uvicorn", "app:app", "--host", "127.0.0.1", "--port", "$Port")
if ($Hidden) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    Start-Process -FilePath $py -ArgumentList $uvArgs -WorkingDirectory $svc -WindowStyle Hidden `
        -RedirectStandardOutput (Join-Path $logDir "mandi-service.log") `
        -RedirectStandardError  (Join-Path $logDir "mandi-service.err.log")
    # The first start loads the model and can take 20 to 40 seconds.
    for ($i = 0; $i -lt 40; $i++) {
        Start-Sleep -Seconds 3
        if (Test-Service $Port) { Write-Host "Price service is up on http://127.0.0.1:$Port"; exit 0 }
    }
    Write-Host "Started, but it did not answer within 2 minutes. See storage\logs\mandi-service.err.log"
    exit 1
}
Write-Host "Starting the price service on http://127.0.0.1:$Port  (press Ctrl+C to stop)"
Set-Location $svc
# uvicorn writes its normal log lines to stderr. Windows PowerShell 5.1 would treat the first one
# as an error (and stop the service) when output is captured, as the scheduled task does.
$ErrorActionPreference = "Continue"
& $py @uvArgs
