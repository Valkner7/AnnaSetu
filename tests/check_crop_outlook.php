<?php
// Tests for the crop price comparison (F2): the maths and the controller action.
// No network, no database. Run: php tests/check_crop_outlook.php
namespace Tests;

define('BASE_URL', '');
require __DIR__ . '/../app/Helpers/Translator.php';
require __DIR__ . '/../app/Helpers/MandiText.php';
require __DIR__ . '/../app/Helpers/CropOutlook.php';
require __DIR__ . '/../app/Services/MandiService.php';
require __DIR__ . '/../app/Controllers/FarmerController.php';

use App\Controllers\FarmerController;
use App\Helpers\CropOutlook;
use App\Helpers\Translator;
use App\Services\MandiService;

$fail = 0;
ob_start();   // keep header() calls legal: results are printed at the very end
$log = '';
function check($name, $ok) { global $fail, $log; $log .= ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }

function series($n, $endDate, $fn) {
    $pts = [];
    $end = strtotime($endDate . ' UTC');
    for ($i = $n - 1; $i >= 0; $i--) {
        $pts[] = ['date' => gmdate('Y-m-d', $end - $i * 86400), 'price' => $fn($n - 1 - $i)];
    }
    return $pts;
}

// --- CropOutlook ---
check('too few points -> no_data', CropOutlook::summarize(series(5, '2026-09-24', fn($i) => 500), '2026-10-18')['status'] === 'no_data');
check('junk rows ignored', CropOutlook::summarize([['date' => 'x', 'price' => 'abc'], 'z', ['date' => '2026-09-24', 'price' => -5]], '2026-10-18')['status'] === 'no_data');

$flat = CropOutlook::summarize(series(120, '2026-09-24', fn($i) => 500), '2026-10-18');
check('flat series ok', $flat['status'] === 'ok');
check('latest date and price', $flat['latest_date'] === '2026-09-24' && $flat['latest_price'] == 500);
check('age counted from today', $flat['age_days'] === 24);
check('flat: 0% vs period, 0% change', $flat['vs_period_pct'] == 0 && $flat['change_90_pct'] == 0);
check('flat: low swings', $flat['swing'] === 'low');

$rise = CropOutlook::summarize(series(120, '2026-09-24', fn($i) => 400 + $i * 2), '2026-09-24');
check('rising: positive change over 90 days', $rise['change_90_pct'] > 0);
check('rising: latest above period average', $rise['vs_period_pct'] > 0);

$wild = CropOutlook::summarize(series(100, '2026-09-24', fn($i) => $i % 2 ? 300 : 700), '2026-09-24');
check('wild series: high swings', $wild['swing'] === 'high');

$dup = CropOutlook::summarize(array_merge(series(12, '2026-09-24', fn($i) => 500), [['date' => '2026-09-24', 'price' => 600]]), '2026-09-24');
check('one price per day, later row wins', $dup['latest_price'] == 600);
check('future-dated latest gives age 0', CropOutlook::summarize(series(12, '2026-09-24', fn($i) => 500), '2026-09-20')['age_days'] === 0);

// --- Controller ---
class FakeSvc extends MandiService {
    public static $calls = [];
    public static $mode = 'ok';   // ok | down
    protected function fetchRemote($path, $params = []) {
        self::$calls[] = $path . ($params ? '?' . http_build_query($params) : '');
        if ($path === '/meta') { return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['reliable_crops' => ['Potato', 'Onion', 'Tomato']]]; }
        if (self::$mode === 'down') { return ['ok' => false, 'status' => 0, 'error' => 'down', 'data' => null]; }
        if ($path === '/history') {
            if ($params['crop'] === 'Tomato') { return ['ok' => false, 'status' => 404, 'error' => 'nf', 'data' => null]; }
            $base = $params['crop'] === 'Potato' ? 500 : 900;
            return ['ok' => true, 'status' => 200, 'error' => null,
                    'data' => ['points' => series(60, '2026-09-24', fn($i) => $base + $i)]];
        }
        return ['ok' => false, 'status' => 404, 'error' => 'nf', 'data' => null];
    }
}
class TestFarmer extends FarmerController {
    public static $dir;
    public function __construct() {}
    protected function mandiService() { return new FakeSvc(['cache_dir' => self::$dir, 'api_base' => 'http://x', 'cache_ttl_seconds' => 3600]); }
}
function run($mandi, $mode = 'ok') {
    FakeSvc::$mode = $mode;
    $dir = TestFarmer::$dir;
    foreach (glob($dir . '/*') ?: [] as $f) { unlink($f); }
    FakeSvc::$calls = [];
    $_POST = ['mandi' => $mandi];
    $c = new TestFarmer();
    ob_start(); $c->getAiRecommendation(); $out = ob_get_clean();
    return [json_decode($out, true), http_response_code()];
}
TestFarmer::$dir = sys_get_temp_dir() . '/crop_outlook_test_' . bin2hex(random_bytes(4));
Translator::load('en');

list($r, $code) = run('Rayya');
check('success for a real mandi', $r['success'] === true && ($code === 200 || $code === false));
check('one row per crop', count($r['crops']) === 3);
check('Potato has figures', $r['crops'][0]['status'] === 'ok' && $r['crops'][0]['latest_price'] == 559);
check('Onion differs from Potato', $r['crops'][1]['latest_price'] == 959);
check('crop not sold here -> no_data', $r['crops'][2]['status'] === 'no_data');
check('asks history for the chosen mandi, 365 days', in_array('/history?crop=Potato&mandi=Rayya&days=365', FakeSvc::$calls, true));
check('no invented revenue or yield fields', !isset($r['crops'][0]['estimated_revenue']) && !isset($r['crops'][0]['estimated_yield']));

list($r, $code) = run('Rayya', 'down');
check('service down with no cache -> 502', $r['success'] === false && $code === 502);

foreach (['', '   ', str_repeat('a', 81), "bad\x00name"] as $bad) {
    list($r, $code) = run($bad);
    check('rejects bad mandi ' . json_encode(substr($bad, 0, 10)), $r['success'] === false && $code === 400);
}
$_POST = [];

ob_end_clean();
echo $log;
echo $fail ? "\nFAILED: $fail\n" : "\nOK: all crop outlook checks passed.\n";
exit($fail ? 1 : 0);
