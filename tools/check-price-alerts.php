<?php
/**
 * Checks every user's active price alerts against the latest mandi prices.
 * Run from the command line (Task Scheduler runs it every 30 minutes, see
 * tools\register-mandi-tasks.ps1):
 *
 *     C:\xampp\php\php.exe tools\check-price-alerts.php
 *
 * Needs MySQL running (XAMPP) and, for fresh prices, the price service
 * (tools\start-mandi-service.ps1; the hosted copy is used if it is not running).
 * Exit code 0 = checked (or nothing to check), 1 = prices could not be fetched or a failure.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

spl_autoload_register(function ($class) {
    if (strncmp('App\\', $class, 4) !== 0) { return; }
    $file = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) { require $file; }
});

$logDir = __DIR__ . '/../storage/logs';
function logLine($msg) {
    global $logDir;
    $line = date('Y-m-d H:i:s') . '  ' . $msg . "\n";
    echo $line;
    if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
        @file_put_contents($logDir . '/price-alerts.log', $line, FILE_APPEND);
    }
}

try {
    $checker = new \App\Services\PriceAlertChecker(new \App\Models\PriceAlert(), new \App\Services\MandiService());
    $s = $checker->run();
} catch (\Throwable $e) {
    logLine('FAILED: ' . $e->getMessage());
    exit(1);
}
logLine(sprintf('Checked %d alert(s) on %d crop/mandi pair(s): %d reached their price, %d could not be priced.',
    $s['checked'], $s['pairs'], $s['triggered'], $s['unavailable']));
exit(($s['unavailable'] > 0 && $s['checked'] === 0) ? 1 : 0);
