<?php
// Tests for "use my location" (F4). No network: a fake service replaces the HTTP call.
// Run: php tests/check_mandi_nearby.php
define('BASE_URL', '/app');
require __DIR__ . '/../app/Helpers/Translator.php';
require __DIR__ . '/../app/Helpers/MandiText.php';
require __DIR__ . '/../app/Services/MandiService.php';
require __DIR__ . '/../app/Controllers/MandiController.php';

use App\Controllers\MandiController;
use App\Services\MandiService;

ob_start();
$log = ''; $fail = 0;
function check($name, $ok) { global $fail, $log; $log .= ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }

class FakeNear extends MandiService {
    public static $calls = [];
    public static $mode = 'ok';   // ok | down | none
    protected function fetchRemote($path, $params = []) {
        self::$calls[] = $path . ($params ? '?' . http_build_query($params) : '');
        if ($path === '/meta') { return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['reliable_crops' => ['Potato']]]; }
        if (self::$mode === 'down') { return ['ok' => false, 'status' => 0, 'error' => 'down', 'data' => null]; }
        $m = [
            ['mandi' => 'Rayya', 'district' => 'Amritsar', 'distance_km' => 12.34, 'latest_price' => null],
            ['mandi' => 'Jalandhar', 'district' => 'Jalandhar', 'distance_km' => 3.21, 'latest_price' => ['date' => '2026-10-18', 'price' => 540]],
            ['mandi' => 'Nakodar', 'district' => 'Jalandhar', 'distance_km' => 20, 'latest_price' => 555.5],
            ['mandi' => 'Phagwara', 'district' => 'Kapurthala', 'distance_km' => 30, 'latest_price' => 'abc'],
            'junk',
            ['mandi' => 'Kapurthala', 'district' => 'Kapurthala', 'distance_km' => 25, 'latest_price' => 530],
            ['mandi' => 'Ludhiana', 'district' => 'Ludhiana', 'distance_km' => 60, 'latest_price' => 580],
            ['mandi' => 'Khanna', 'district' => 'Ludhiana', 'distance_km' => 80, 'latest_price' => 600],
            ['mandi' => 'Rajpura', 'district' => 'Patiala', 'distance_km' => 120, 'latest_price' => 610],
            ['mandi' => 'Zira', 'district' => 'Ferozepur', 'distance_km' => 150, 'latest_price' => 620],
        ];
        if (self::$mode === 'none') { $m = [['mandi' => 'Rayya', 'distance_km' => 5, 'latest_price' => null]]; }
        return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['mandis' => $m]];
    }
}
class TestNear extends MandiController {
    public static $dir;
    public function __construct() {}
    protected function service() { return new FakeNear(['cache_dir' => self::$dir, 'api_base' => 'http://x', 'cache_ttl_seconds' => 3600]); }
}
TestNear::$dir = sys_get_temp_dir() . '/mandi_near_test_' . bin2hex(random_bytes(4));
function call($get, $mode = 'ok') {
    foreach (glob(TestNear::$dir . '/*') ?: [] as $f) { unlink($f); }
    FakeNear::$mode = $mode; FakeNear::$calls = [];
    $_GET = $get;
    ob_start(); (new TestNear())->nearby(); $out = ob_get_clean();
    return [json_decode($out, true), http_response_code()];
}
function nearCalls() { return array_values(array_filter(FakeNear::$calls, function ($c) { return strpos($c, '/api/nearby-mandis') === 0; })); }

// parseLocation
$p = [MandiController::class, 'parseLocation'];
check('location: valid, rounded to 2 decimals', $p('31.32456', '75.57712') === [31.32, 75.58]);
check('location: negative rejected (outside India box)', $p('-31.3', '75.5') === null);
check('location: outside India rejected', $p('51.5', '-0.12') === null);
check('location: text rejected', $p('abc', '75') === null);
check('location: injection-like rejected', $p('31.3&x=1', '75') === null);
check('location: exponent rejected', $p('3e1', '75') === null);
check('location: missing rejected', $p(null, '75') === null);
check('location: array rejected', $p(['31'], '75') === null);

// nearby()
list($r, $code) = call(['crop' => 'Potato', 'lat' => '31.3245', 'lon' => '75.5771']);
check('ok response', $r['ok'] === true);
$rows = $r['data']['mandis'];
check('only mandis with a numeric price kept', !in_array('Rayya', array_column($rows, 'mandi'), true) && !in_array('Phagwara', array_column($rows, 'mandi'), true));
check('at most 5 returned', count($rows) === 5);
check('nearest first', array_column($rows, 'mandi') === ['Jalandhar', 'Nakodar', 'Kapurthala', 'Ludhiana', 'Khanna']);
check('price given as {date, price} (the real service shape) is read, with its date', $rows[0]['mandi'] === 'Jalandhar' && $rows[0]['latest_price'] == 540 && $rows[0]['latest_date'] === '2026-10-18');
check('plain numeric price still works and has no date', $rows[1]['latest_price'] === 555.5 && $rows[1]['latest_date'] === null);
check('distance rounded to 1 decimal', $rows[0]['distance_km'] == 3.2);
check('coordinates sent rounded to 2 decimals, with the crop', in_array('/api/nearby-mandis?lat=31.32&lon=75.58&crop=Potato&limit=15', FakeNear::$calls, true));
list($r) = call(['crop' => 'Potato', 'lat' => '31.3', 'lon' => '75.5'], 'none');
check('no priced mandis -> ok with empty list', $r['ok'] === true && $r['data']['mandis'] === []);
list($r, $code) = call(['crop' => 'Potato', 'lat' => '31.3', 'lon' => '75.5'], 'down');
check('outage -> 502 and a readable message', $r['ok'] === false && $code === 502 && strpos($r['error'], 'Nearby') === 0);
list($r, $code) = call(['crop' => 'Wheat', 'lat' => '31.3', 'lon' => '75.5']);
check('unsupported crop -> 400, service not asked', $code === 400 && nearCalls() === []);
list($r, $code) = call(['crop' => 'Potato', 'lat' => '51.5', 'lon' => '-0.1']);
check('location outside India -> 400, service not asked', $code === 400 && nearCalls() === []);
list($r, $code) = call(['crop' => '', 'lat' => '31.3', 'lon' => '75.5']);
check('no crop -> 400', $code === 400);

ob_end_clean();
echo $log;
echo $fail ? "\nFAILED: $fail\n" : "\nOK: all nearby checks passed.\n";
exit($fail ? 1 : 0);
