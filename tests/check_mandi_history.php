<?php
// Tests for the price-history action behind the chart (F1).
// No network: a fake service replaces the HTTP call. Run: php tests/check_mandi_history.php
namespace Tests;

define('BASE_URL', '');
require __DIR__ . '/../app/Services/MandiService.php';
require __DIR__ . '/../app/Controllers/MandiController.php';

use App\Controllers\MandiController;
use App\Services\MandiService;

class FakeMandi extends MandiService {
    public static $calls = [];
    public static $mode = 'ok';   // ok | down | notfound | junk
    protected function fetchRemote($path, $params = []) {
        self::$calls[] = $path . ($params ? '?' . http_build_query($params) : '');
        if ($path === '/meta' && !$params) {
            return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['reliable_crops' => ['Potato', 'Onion']]];
        }
        if ($path === '/history') {
            if (self::$mode === 'down') { return ['ok' => false, 'status' => 0, 'error' => 'down', 'data' => null]; }
            if (self::$mode === 'notfound') { return ['ok' => false, 'status' => 404, 'error' => 'nf', 'data' => null]; }
            $pts = self::$mode === 'junk'
                ? [['date' => '2026-09-24', 'price' => 'abc'], ['date' => 5, 'price' => 1], 'x', ['date' => '2026-09-23', 'price' => '580.5']]
                : [['date' => '2026-09-23', 'price' => 580], ['date' => '2026-09-24', 'price' => 582]];
            return ['ok' => true, 'status' => 200, 'error' => null,
                'data' => ['crop' => $params['crop'], 'mandi' => $params['mandi'], 'unit' => 'INR per quintal', 'points' => $pts]];
        }
        return ['ok' => false, 'status' => 404, 'error' => 'nf', 'data' => null];
    }
}

$dir = sys_get_temp_dir() . '/mandi_hist_test_' . bin2hex(random_bytes(4));
function fresh($dir) { foreach (glob($dir . '/*') ?: [] as $f) { unlink($f); } FakeMandi::$calls = []; }

class TestController extends MandiController {
    public static $dir;
    public function __construct() { /* no login redirect in the test */ }
    protected function service() { return new FakeMandi(['cache_dir' => self::$dir, 'api_base' => 'http://x', 'cache_ttl_seconds' => 3600]); }
}
TestController::$dir = $dir;

$fails = 0;
function check($name, $cond, $extra = '') { global $fails; fwrite(STDOUT, ($cond ? 'PASS ' : 'FAIL ') . $name . ($cond ? '' : "  -> $extra") . "\n"); if (!$cond) { $fails++; } }
function run($get) {
    $_GET = $get;
    $c = new TestController();
    ob_start(); $c->history(); $out = ob_get_clean();
    return json_decode($out, true);
}
function historyCalls() { return array_values(array_filter(FakeMandi::$calls, function ($c) { return strpos($c, '/history') === 0; })); }

// Happy path
fresh($dir); FakeMandi::$mode = 'ok';
$r = run(['crop' => 'Potato', 'mandi' => 'Rayya']);
check('returns ok with points', $r['ok'] === true && count($r['data']['points']) === 2, json_encode($r));
check('prices are numbers', is_numeric($r['data']['points'][1]['price']) && $r['data']['points'][1]['price'] == 582);
check('default is 60 days', historyCalls() === ['/history?crop=Potato&mandi=Rayya&days=60'], json_encode(historyCalls()));

// days clamped to 7..365
fresh($dir); run(['crop' => 'Potato', 'mandi' => 'Rayya', 'days' => '1']);
check('days below 7 become 7', historyCalls() === ['/history?crop=Potato&mandi=Rayya&days=7'], json_encode(historyCalls()));
fresh($dir); run(['crop' => 'Potato', 'mandi' => 'Rayya', 'days' => '9999']);
check('days above 365 become 365', historyCalls() === ['/history?crop=Potato&mandi=Rayya&days=365'], json_encode(historyCalls()));
fresh($dir); run(['crop' => 'Potato', 'mandi' => 'Rayya', 'days' => ['x']]);
check('array days falls back safely', historyCalls() === ['/history?crop=Potato&mandi=Rayya&days=60'], json_encode(historyCalls()));

// Different pairs never share a cache entry (R5 safety)
fresh($dir);
$a = run(['crop' => 'Potato', 'mandi' => 'Rayya']);
$b = run(['crop' => 'Onion', 'mandi' => 'Khanna']);
check('pairs are separate', $a['data']['mandi'] === 'Rayya' && $b['data']['mandi'] === 'Khanna' && $b['data']['crop'] === 'Onion');

// Validation
fresh($dir);
check('unknown crop rejected, no history call', run(['crop' => 'Wheat', 'mandi' => 'Rayya'])['ok'] === false && historyCalls() === []);
check('missing mandi rejected', run(['crop' => 'Potato'])['ok'] === false);
check('array crop rejected', run(['crop' => ['Potato'], 'mandi' => 'Rayya'])['ok'] === false);

// Errors
fresh($dir); FakeMandi::$mode = 'notfound';
$r = run(['crop' => 'Potato', 'mandi' => 'Nowhere']);
check('upstream 404 gives a readable error', $r['ok'] === false && strpos($r['error'], 'not available') !== false);
fresh($dir); FakeMandi::$mode = 'ok'; run(['crop' => 'Potato', 'mandi' => 'Rayya']);
FakeMandi::$mode = 'down';
$svc = new FakeMandi(['cache_dir' => $dir, 'api_base' => 'http://x', 'cache_ttl_seconds' => 0]);
$sr = $svc->getHistory('Potato', 'Rayya', 60);
check('outage serves the last cached history marked stale', $sr['ok'] && $sr['stale'] === true);
fresh($dir); FakeMandi::$mode = 'down';
check('outage with no cache is an error', run(['crop' => 'Potato', 'mandi' => 'Rayya'])['ok'] === false);

// Junk upstream points are dropped
fresh($dir); FakeMandi::$mode = 'junk';
$r = run(['crop' => 'Potato', 'mandi' => 'Rayya']);
check('junk points dropped, numeric string kept', $r['ok'] && count($r['data']['points']) === 1 && $r['data']['points'][0]['price'] == 580.5, json_encode($r));

foreach (glob($dir . '/*') ?: [] as $f) { unlink($f); } @rmdir($dir);
exit($fails ? 1 : 0);
