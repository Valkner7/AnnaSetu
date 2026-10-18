# Refreshes ai\mandi_service from the Mandi Setu repo.
# Read-only on the source repo: it clones into a temp folder and never commits or pushes there.
# Run from the AnnaSetu folder:  powershell -ExecutionPolicy Bypass -File tools\sync-mandi-service.ps1
param(
    [string]$Source = "https://github.com/Valkner7/mandi-price-forecast.git",
    [string]$Branch = "main"
)
$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$dest = Join-Path $root "ai\mandi_service"
$tmp  = Join-Path ([System.IO.Path]::GetTempPath()) ("mandi-sync-" + [guid]::NewGuid().ToString("N"))

# autocrlf off so the copy keeps LF line endings (the source repo's .gitattributes asks for LF).
git -c core.autocrlf=false clone --quiet --depth 1 --branch $Branch $Source $tmp
if ($LASTEXITCODE -ne 0) { throw "git clone failed" }

$commit = (git -C $tmp rev-parse --short HEAD).Trim()

# Same exclusions as the first import: git data, the source repo's workflows, the React source.
foreach ($name in @(".git", ".github", "frontend")) {
    $p = Join-Path $tmp $name
    if (Test-Path $p) { Remove-Item $p -Recurse -Force }
}

if (Test-Path $dest) { Remove-Item $dest -Recurse -Force }
New-Item -ItemType Directory -Path (Split-Path -Parent $dest) -Force | Out-Null
Copy-Item -Path $tmp -Destination $dest -Recurse
Remove-Item $tmp -Recurse -Force

$today = Get-Date -Format "yyyy-MM-dd"
$note = @"
# Upstream

This folder is a **snapshot copy** of the Mandi Setu price service.

- Source: $Source (branch ``$Branch``)
- Snapshot commit: ``$commit``
- Taken: $today
- Left out: ``.git``, ``.github`` (its workflows run in the source repo, not here) and ``frontend/`` (React dashboard source; the built dashboard in ``static/dashboard`` is kept so the service starts normally).

The source repo is the one that fetches new prices every day and retrains the model. Do not edit files here: make changes in the source repo, then refresh this copy with:

``````powershell
powershell -ExecutionPolicy Bypass -File tools\sync-mandi-service.ps1
``````

The script only reads from the source repo. It never commits or pushes there.
"@
Set-Content -Path (Join-Path $dest "UPSTREAM.md") -Value $note -Encoding UTF8

Write-Host "Copied Mandi Setu commit $commit into ai\mandi_service."
Write-Host "Next: git status, review, then git add ai tools; git commit; git push origin main"
