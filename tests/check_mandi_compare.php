<?php
// Tests for comparing mandis (F5). No network: a fake service replaces the HTTP call.
// Run: php tests/check_mandi_compare.php

define('BASE_URL', '/app');
require __DIR__ . '/../app/Helpers/Translator.php';
require __DIR__ . '/../app/Helpers/MandiText.php';
require __DIR__ . '/../app/Services/MandiService.php';
require __DIR__ . '/../app/Controllers/MandiController.php';

use App\Controllers\MandiController;
use App\Helpers\Translator;
use App\Services\MandiService;

ob_start();   // keep header() legal; results are printed at the end
$log = ''; $fail = 0;
function check($name, $ok) { global $fail, $log; $log .= ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }
function __($key) { return Translator::get($key); }
function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

class FakeCmp extends MandiService {
    public static $calls = [];
    public static $mode = 'ok';   // ok | down | notfound | junk | skipped
    protected function fetchRemote($path, $params = []) {
        self::$calls[] = $path . ($params ? '?' . http_build_query($params) : '');
        if ($path === '/meta' && !$params) { return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['reliable_crops' => ['Potato', 'Onion']]]; }
        if ($path !== '/compare') { return ['ok' => false, 'status' => 404, 'error' => 'nf', 'data' => null]; }
        if (self::$mode === 'down') { return ['ok' => false, 'status' => 0, 'error' => 'down', 'data' => null]; }
        if (self::$mode === 'notfound') { return ['ok' => false, 'status' => 404, 'error' => 'nf', 'data' => null]; }
        $rows = [
            ['mandi' => 'Rayya', 'latest_price' => 540, 'latest_date' => '2026-09-24', 'trend' => 'stable'],
            ['mandi' => 'Khanna', 'latest_price' => 600, 'latest_date' => '2026-09-20', 'trend' => 'rising'],
            ['mandi' => 'Ludhiana', 'latest_price' => 580.5, 'latest_date' => '2026-09-24', 'trend' => 'falling'],
        ];
        if (self::$mode === 'junk') { $rows[] = ['mandi' => 5, 'latest_price' => 1]; $rows[] = 'x'; $rows[] = ['mandi' => 'Bad', 'latest_price' => 'abc']; }
        $data = ['crop' => $params['crop'], 'results' => $rows, 'unit' => 'INR per quintal'];
        if (self::$mode === 'skipped') { $data['skipped'] = [['mandi' => 'Nowhere', 'detail' => 'no data']]; }
        return ['ok' => true, 'status' => 200, 'error' => null, 'data' => $data];
    }
}
class TestCtl extends MandiController {
    public static $dir;
    public function __construct() {}
    protected function service() { return new FakeCmp(['cache_dir' => self::$dir, 'api_base' => 'http://x', 'cache_ttl_seconds' => 3600]); }
    public function exposeCrops() { return $this->allowedCrops(false); }
}
TestCtl::$dir = sys_get_temp_dir() . '/mandi_cmp_test_' . bin2hex(random_bytes(4));
function call($crop, $mandis, $mode = 'ok', $lang = 'en') {
    foreach (glob(TestCtl::$dir . '/*') ?: [] as $f) { unlink($f); }
    FakeCmp::$mode = $mode; FakeCmp::$calls = [];
    Translator::load($lang);
    $_GET = ['crop' => $crop, 'mandis' => $mandis];
    ob_start(); (new TestCtl())->comparison(); $out = ob_get_clean();
    return [json_decode($out, true), http_response_code()];
}

// parseMandiNames
$p = [MandiController::class, 'parseMandiNames'];
check('parse: two names', $p('Rayya,Khanna') === ['Rayya', 'Khanna']);
check('parse: trims and de-duplicates ignoring case', $p(' Rayya , rayya, Khanna ') === ['Rayya', 'Khanna']);
check('parse: one name is not enough', $p('Rayya') === null);
check('parse: only duplicates is not enough', $p('Rayya,rayya') === null);
check('parse: six names rejected', $p('a,b,c,d,e,f') === null);
check('parse: five names ok', count($p('a,b,c,d,e')) === 5);
check('parse: empty entry rejected', $p('Rayya,,Khanna') === null);
check('parse: control character rejected', $p("Rayya,Kha\x00nna") === null);
check('parse: html characters rejected', $p('Rayya,<b>') === null);
check('parse: long name rejected', $p('Rayya,' . str_repeat('a', 81)) === null);
check('parse: array rejected', $p(['Rayya', 'Khanna']) === null);
check('parse: null rejected', $p(null) === null);

// comparison()
list($r, $code) = call('Potato', 'Rayya,Khanna,Ludhiana');
check('ok response', $r['ok'] === true);
$rows = $r['data']['rows'];
check('ranked highest price first', array_column($rows, 'mandi') === ['Khanna', 'Ludhiana', 'Rayya']);
check('best mandi', $r['data']['best_mandi'] === 'Khanna');
check('spread = best - worst', $r['data']['spread'] == 60 && $r['data']['spread_pct'] == 11.1);
check('dates differ by 4 days', $r['data']['date_spread_days'] === 4);
check('trend label present', $rows[0]['trend_label'] === 'rising');
check('service asked once, names sorted for the cache', in_array('/compare?crop=Potato&mandis=Khanna%2CLudhiana%2CRayya', FakeCmp::$calls, true));

list($r) = call('Potato', 'Rayya,Khanna,Ludhiana', 'ok', 'hi');
check('Hindi trend label', $r['data']['rows'][0]['trend_label'] === Translator::get('mp_trend_rising') && $r['data']['rows'][0]['trend_label'] !== 'rising');

list($r) = call('Potato', 'Rayya,Khanna,Ludhiana', 'junk');
check('junk rows dropped', count($r['data']['rows']) === 3);
list($r) = call('Potato', 'Rayya,Khanna,Nowhere', 'skipped');
check('skipped mandis listed', $r['data']['skipped'] === ['Nowhere']);
list($r, $code) = call('Potato', 'Rayya,Khanna', 'down');
check('outage with no cache -> 502', $r['ok'] === false && $code === 502);
list($r, $code) = call('Potato', 'Rayya,Khanna', 'notfound');
check('no data -> readable 502 message', $r['ok'] === false && $code === 502 && strpos($r['error'], 'No comparison') === 0);
list($r, $code) = call('Wheat', 'Rayya,Khanna');
check('unsupported crop -> 400', $r['ok'] === false && $code === 400);
check('unsupported crop never reaches /compare', !array_filter(FakeCmp::$calls, fn($c) => strpos($c, '/compare') === 0));
list($r, $code) = call('Potato', 'Rayya');
check('one mandi -> 400', $r['ok'] === false && $code === 400);
list($r, $code) = call('', 'Rayya,Khanna');
check('no crop -> 400', $r['ok'] === false && $code === 400);

// Stale fallback: second call during an outage is served from cache.
$dir = TestCtl::$dir; foreach (glob($dir . '/*') ?: [] as $f) { unlink($f); }
FakeCmp::$mode = 'ok'; Translator::load('en'); $_GET = ['crop' => 'Potato', 'mandis' => 'Rayya,Khanna'];
ob_start(); (new TestCtl())->comparison(); ob_end_clean();
FakeCmp::$mode = 'down';
$svc = new FakeCmp(['cache_dir' => $dir, 'api_base' => 'http://x', 'cache_ttl_seconds' => 0]);
$res = $svc->getCompare('Potato', ['Khanna', 'Rayya']);
check('same mandis in another order hit the same cache (stale on outage)', $res['ok'] && $res['stale']);

// Page renders in each language with no raw keys.
$crops = ['Potato', 'Onion'];
foreach (['en', 'hi', 'pa'] as $l) {
    Translator::load($l);
    ob_start(); require __DIR__ . '/../views/mandi/compare.php'; $html = ob_get_clean();
    $visible = preg_replace('/<script.*?<\/script>/s', '', $html);
    check("$l page renders", strlen($html) > 1500);
    check("$l page shows no raw key", !preg_match('/\b(cf|cp|mp)_[a-z_]+/', $visible));
    check("$l page has the translated title", strpos($html, e(Translator::get('cf_title'))) !== false);
}

ob_end_clean();
echo $log;
echo $fail ? "\nFAILED: $fail\n" : "\nOK: all compare checks passed.\n";
exit($fail ? 1 : 0);
