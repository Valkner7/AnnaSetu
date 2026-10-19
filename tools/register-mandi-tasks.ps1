<#
Sets up (or removes) the three Windows scheduled tasks for Mandi Setu, for YOUR user
(no administrator needed). Run once from the AnnaSetu folder:

    powershell -ExecutionPolicy Bypass -File tools\register-mandi-tasks.ps1

Tasks created:
  AnnaSetu - Price service        starts the local price service when you log in
                                  (runs tools\start-mandi-service.ps1 in a hidden window)
  AnnaSetu - Daily mandi fetch    every day at 22:30 (or when the PC is next on): fetch today's
                                  prices and refresh the local copy (tools\mandi-daily-job.ps1)
  AnnaSetu - Price alerts check   every 30 minutes: tools\check-price-alerts.php
                                  (needs MySQL running in XAMPP)

Options:
  -Remove                 delete the three tasks again
  -PhpPath <file>         php.exe to use (default C:\xampp\php\php.exe)
  -MandiRepo <folder>     your clone of mandi-price-forecast (default C:\xampp\htdocs\mandi-fetch)
  -Python <name or path>  Python used by the fetch (default python)
  -FetchTime HH:mm        time of the daily fetch (default 22:30)
  -Retrain                also retrain the forecast model after each daily fetch

Test a task right away:   Start-ScheduledTask -TaskName "AnnaSetu - Daily mandi fetch"
Logs: storage\logs\ (mandi-service.log, mandi-daily-job.log, price-alerts.log)
Each run of a hidden task may flash a window for a moment; that is normal.
#>
param(
    [switch]$Remove,
    [string]$PhpPath = "C:\xampp\php\php.exe",
    [string]$MandiRepo = "C:\xampp\htdocs\mandi-fetch",
    [string]$Python = "python",
    [string]$FetchTime = "22:30",
    [switch]$Retrain
)
$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$tools = Join-Path $root "tools"
$logs = Join-Path (Join-Path $root "storage") "logs"
$psExe = "powershell.exe"

$nameService = "AnnaSetu - Price service"
$nameFetch   = "AnnaSetu - Daily mandi fetch"
$nameAlerts  = "AnnaSetu - Price alerts check"
$allNames = @($nameService, $nameFetch, $nameAlerts)

if ($Remove) {
    foreach ($n in $allNames) {
        if (Get-ScheduledTask -TaskName $n -ErrorAction SilentlyContinue) {
            Unregister-ScheduledTask -TaskName $n -Confirm:$false
            Write-Host "Removed: $n"
        } else {
            Write-Host "Not found (nothing to remove): $n"
        }
    }
    exit 0
}

if ($FetchTime -notmatch '^([01]?\d|2[0-3]):[0-5]\d$') { throw "-FetchTime must look like 22:30 (24-hour clock)." }
foreach ($f in @("start-mandi-service.ps1", "mandi-daily-job.ps1", "check-price-alerts.php", "daily-mandi-fetch.ps1")) {
    if (-not (Test-Path (Join-Path $tools $f))) { throw "tools\$f was not found. Run this from the AnnaSetu folder after applying all patches." }
}
if (-not (Test-Path $PhpPath)) { Write-Warning "PHP was not found at $PhpPath. The alerts task will fail until you pass the right -PhpPath." }
if (-not (Test-Path -LiteralPath ($MandiRepo.TrimEnd("\", "/") + "\.git"))) {
    Write-Warning "No git clone at $MandiRepo. The daily fetch will fail until you run:  git clone https://github.com/Valkner7/mandi-price-forecast.git `"$MandiRepo`""
}
New-Item -ItemType Directory -Path $logs -Force | Out-Null

$user = "$env:USERDOMAIN\$env:USERNAME"
$principal = New-ScheduledTaskPrincipal -UserId $user -LogonType Interactive -RunLevel Limited
$noLimit = New-TimeSpan -Seconds 0

# --- 1. Price service at logon. The task keeps running as long as the service does,
#        so stopping the task (or logging out) stops the service.
$svcScript = Join-Path $tools "start-mandi-service.ps1"
$svcLog = Join-Path $logs "mandi-service-task.log"
$svcArgs = '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -Command "& ''{0}'' *> ''{1}''"' -f $svcScript, $svcLog
$svcAction = New-ScheduledTaskAction -Execute $psExe -Argument $svcArgs -WorkingDirectory $root
$svcTrigger = New-ScheduledTaskTrigger -AtLogOn -User $user
$svcSettings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -ExecutionTimeLimit $noLimit -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -MultipleInstances IgnoreNew

# --- 2. Daily fetch
$jobScript = Join-Path $tools "mandi-daily-job.ps1"
$jobArgs = '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "{0}" -MandiRepo "{1}" -Python "{2}"' -f $jobScript, $MandiRepo, $Python
if ($Retrain) { $jobArgs += " -Retrain" }
$jobAction = New-ScheduledTaskAction -Execute $psExe -Argument $jobArgs -WorkingDirectory $root
$jobTrigger = New-ScheduledTaskTrigger -Daily -At $FetchTime
$jobSettings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 1) -MultipleInstances IgnoreNew

# --- 3. Price alerts every 30 minutes
$alertScript = Join-Path $tools "check-price-alerts.php"
$alertArgs = '-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -Command "& ''{0}'' ''{1}''; exit $LASTEXITCODE"' -f $PhpPath, $alertScript
$alertAction = New-ScheduledTaskAction -Execute $psExe -Argument $alertArgs -WorkingDirectory $root
$alertTrigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(2) `
    -RepetitionInterval (New-TimeSpan -Minutes 30) -RepetitionDuration (New-TimeSpan -Days 3650)
$alertSettings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
    -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 10) -MultipleInstances IgnoreNew

$defs = @(
    @{ Name = $nameService; Action = $svcAction;   Trigger = $svcTrigger;   Settings = $svcSettings;   Desc = "Starts the local Mandi Setu price service for AnnaSetu." },
    @{ Name = $nameFetch;   Action = $jobAction;   Trigger = $jobTrigger;   Settings = $jobSettings;   Desc = "Fetches today's mandi prices and refreshes the local copy." },
    @{ Name = $nameAlerts;  Action = $alertAction; Trigger = $alertTrigger; Settings = $alertSettings; Desc = "Checks AnnaSetu price alerts every 30 minutes." }
)
foreach ($d in $defs) {
    Register-ScheduledTask -TaskName $d.Name -Action $d.Action -Trigger $d.Trigger -Settings $d.Settings `
        -Principal $principal -Description $d.Desc -Force | Out-Null
    Write-Host "Registered: $($d.Name)"
}

Write-Host ""
Write-Host "Done. Tasks run as $user. The price service starts at your next log-in;"
Write-Host "to start it now:  Start-ScheduledTask -TaskName `"$nameService`""
Write-Host "To undo everything: powershell -ExecutionPolicy Bypass -File tools\register-mandi-tasks.ps1 -Remove"
