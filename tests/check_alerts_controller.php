<?php
// Tests for AlertsController: validation, ownership, POST-only, and the page render. No network or MySQL.
// Run: php tests/check_alert_controller.php
if (!extension_loaded('pdo_sqlite')) { echo "SKIP: pdo_sqlite is not installed.\n"; exit(0); }
define('BASE_URL', '/app');
require __DIR__ . '/../app/Helpers/Translator.php';
require __DIR__ . '/../app/Helpers/MandiText.php';
require __DIR__ . '/../app/Helpers/PriceAlertRules.php';
require __DIR__ . '/../app/Helpers/Validator.php';
require __DIR__ . '/../app/Core/Database.php';
require __DIR__ . '/../app/Models/PriceAlert.php';
require __DIR__ . '/../app/Helpers/MandiConfig.php';
require __DIR__ . '/../app/Services/MandiService.php';
require __DIR__ . '/../app/Services/PriceAlertChecker.php';
require __DIR__ . '/../app/Controllers/AlertsController.php';

use App\Controllers\AlertsController;
use App\Models\PriceAlert;
use App\Services\MandiService;

function __($k) { return \App\Helpers\Translator::get($k); }
function e($v): string { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
\App\Helpers\Translator::load('en');

ob_start();   // buffer everything: header()/http_response_code() only work before output starts
$fail = 0; $log = '';
function check($name, $ok, $extra = '') { global $fail, $log; $log .= ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok ? '' : "  -> $extra") . "\n"; if (!$ok) { $fail++; } }

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE price_alerts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, crop TEXT NOT NULL, mandi TEXT NOT NULL, direction TEXT NOT NULL, target_price REAL NOT NULL, status TEXT NOT NULL DEFAULT 'active', last_price REAL, last_price_date TEXT, last_checked_at TEXT, triggered_price REAL, triggered_price_date TEXT, triggered_at TEXT, seen INT NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT)");

class FakeSvc extends MandiService {
    public static $metaDown = false;
    protected function fetchRemote($path, $params = []) {
        if ($path === '/meta') {
            if (self::$metaDown) { return ['ok' => false, 'status' => 0, 'error' => 'down', 'data' => null]; }
            return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['reliable_crops' => ['Potato', 'Onion'], 'mandis' => ['Rayya', 'Khanna']]];
        }
        if ($path === '/history') {
            return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['points' => [['date' => gmdate('Y-m-d'), 'price' => 900]]]];
        }
        return ['ok' => false, 'status' => 404, 'error' => 'x', 'data' => null];
    }
}
class TestAlerts extends AlertsController {
    public static $pdo; public static $uid = 1; public static $cache;
    public function __construct() { $this->userId = self::$uid; }
    protected function service() { return new FakeSvc(['cache_dir' => TestAlerts::$cache ?: (sys_get_temp_dir() . '/ac_' . getmypid()), 'cache_ttl_seconds' => 0]); }
    protected function alerts() { return new PriceAlert(self::$pdo); }
}
TestAlerts::$pdo = $pdo;
function call($action, $post, $uid = 1, $method = 'POST') {
    TestAlerts::$uid = $uid;
    $_POST = $post; $_SERVER['REQUEST_METHOD'] = $method;
    ob_start(); (new TestAlerts())->$action(); $out = ob_get_clean();
    return [json_decode($out, true), http_response_code()];
}
$ok = ['crop' => 'Potato', 'mandi' => 'rayya', 'direction' => 'above', 'target_price' => '800'];

list($r, $c) = call('create', $ok);
check('valid alert is created (mandi spelling fixed to the service name)', $r['ok'] === true && $c === 200 && $pdo->query("SELECT mandi FROM price_alerts WHERE id = " . (int) $r['id'])->fetchColumn() === 'Rayya');
list($r, $c) = call('create', $ok);
check('same alert twice is refused', $r['ok'] === false && $c === 400 && stripos($r['error'], 'already') !== false);
list($r, $c) = call('create', array_merge($ok, ['crop' => 'Wheat']));
check('crop the service cannot forecast is refused', $r['ok'] === false && $c === 400);
list($r, $c) = call('create', array_merge($ok, ['mandi' => 'Delhi']));
check('unknown mandi is refused', $r['ok'] === false && $c === 400);
foreach (['0', '-5', 'abc', '1e9', '200001', '', '12; DROP TABLE x'] as $bad) {
    list($r, $c) = call('create', array_merge($ok, ['target_price' => $bad, 'mandi' => 'Khanna']));
    check("target price '$bad' refused", $r['ok'] === false && $c === 400);
}
list($r, $c) = call('create', array_merge($ok, ['direction' => 'sideways', 'mandi' => 'Khanna']));
check('bad direction refused', $r['ok'] === false && $c === 400);
list($r, $c) = call('create', ['crop' => ['x'], 'mandi' => 'Khanna', 'direction' => 'above', 'target_price' => '5']);
check('array input refused', $r['ok'] === false && $c === 400);
list($r, $c) = call('create', array_merge($ok, ['mandi' => 'Khanna']), 1, 'GET');
check('GET is not allowed', $c === 405);
FakeSvc::$metaDown = true;
list($r, $c) = call('create', array_merge($ok, ['mandi' => 'Khanna', 'target_price' => '700']));
check('service down but a saved copy exists: still works (stale list)', $r['ok'] === true);
TestAlerts::$cache = sys_get_temp_dir() . '/ac_empty_' . getmypid() . '_' . mt_rand();
list($r, $c) = call('create', array_merge($ok, ['mandi' => 'Khanna', 'target_price' => '701']));
check('service down and nothing saved -> readable 502', $r['ok'] === false && $c === 502);
FakeSvc::$metaDown = false;
TestAlerts::$cache = null;

for ($i = 0; $i < 25; $i++) { list($r, $c) = call('create', array_merge($ok, ['mandi' => 'Khanna', 'target_price' => (string) (100 + $i)])); }
check('active alerts are capped at 20 per user', $pdo->query("SELECT COUNT(*) FROM price_alerts WHERE user_id = 1")->fetchColumn() == 20 && $c === 400);

$mine = (int) $pdo->query("SELECT MIN(id) FROM price_alerts WHERE user_id = 1")->fetchColumn();
list($r, $c) = call('delete', ['id' => (string) $mine], 2);
check("another user cannot delete my alert", $r['ok'] === false && $c === 404 && $pdo->query("SELECT COUNT(*) FROM price_alerts WHERE id = $mine")->fetchColumn() == 1);
list($r, $c) = call('delete', ['id' => 'abc'], 1);
check('bad id -> 404', $c === 404);
list($r, $c) = call('delete', ['id' => (string) $mine], 1);
check('owner can delete', $r['ok'] === true);

list($r, $c) = call('check', [], 1);
check('check now evaluates only my alerts and triggers those at or below the price', $r['ok'] === true && $r['data']['triggered'] >= 1 && $pdo->query("SELECT COUNT(*) FROM price_alerts WHERE user_id = 2 AND status = 'triggered'")->fetchColumn() == 0);

// page render
$pdo->exec("INSERT INTO price_alerts (user_id, crop, mandi, direction, target_price, status, seen, triggered_price, triggered_price_date) VALUES (7, 'Potato', '<script>alert(1)</script>', 'above', 800, 'triggered', 0, 900, '2026-10-18')");
$_GET = []; $_SERVER['REQUEST_METHOD'] = 'GET';
$crops = ['Potato']; $myAlerts = (new PriceAlert($pdo))->listByUser(7); $newlyReached = 1; $maxActive = 20;
ob_start(); require __DIR__ . '/../views/mandi/alerts.php'; $html = ob_get_clean();
check('page renders', strpos($html, 'Price Alerts') !== false && strpos($html, 'Reached') !== false);
check('user-supplied text is escaped', strpos($html, '<script>alert(1)</script>') === false && strpos($html, '&lt;script&gt;') !== false);

ob_end_clean();
echo $log, $fail === 0 ? "\nOK: all alert controller checks passed.\n" : "\n$fail check(s) FAILED.\n";
exit($fail === 0 ? 0 : 1);
