<?php
// Tests for the crop whitelist coming from the price service (M9).
// No network: fake services replace the HTTP call. Run: php tests/check_mandi_crops.php
namespace Tests;

define('BASE_URL', '');
require __DIR__ . '/../app/Services/MandiService.php';
require __DIR__ . '/../app/Controllers/MandiController.php';

use App\Controllers\MandiController;
use App\Services\MandiService;

class FakeMandi extends MandiService {
    public static $calls = [];       // every upstream request, as "path?params"
    public static $meta;             // response for /meta without crop
    public static $mandis = ['Rayya', 'Khanna'];
    protected function fetchRemote($path, $params = []) {
        self::$calls[] = $path . ($params ? '?' . http_build_query($params) : '');
        if (self::$meta === 'DOWN') { return ['ok' => false, 'status' => 0, 'error' => 'down', 'data' => null]; }
        if ($path === '/meta' && !$params) { return ['ok' => true, 'status' => 200, 'error' => null, 'data' => self::$meta]; }
        if ($path === '/meta') { return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['mandis' => self::$mandis]]; }
        if ($path === '/predict' && ($params['mandi'] ?? '') === 'Nowhere') { return ['ok' => false, 'status' => 404, 'error' => 'nf', 'data' => null]; }
        if ($path === '/predict' && ($params['mandi'] ?? '') === 'Broken') { return ['ok' => false, 'status' => 503, 'error' => 'down', 'data' => null]; }
        if ($path === '/predict') { return ['ok' => true, 'status' => 200, 'error' => null,
            'data' => ['crop' => $params['crop'], 'mandi' => $params['mandi'], 'latest_price' => 582, 'forecast' => []]]; }
        return ['ok' => false, 'status' => 404, 'error' => 'nf', 'data' => null];
    }
}

$dir = sys_get_temp_dir() . '/mandi_crops_test_' . bin2hex(random_bytes(4));
function fresh($dir) { foreach (glob($dir . '/*') ?: [] as $f) { unlink($f); } FakeMandi::$calls = []; }
function svc($dir) { return new FakeMandi(['cache_dir' => $dir, 'api_base' => 'http://x', 'cache_ttl_seconds' => 3600]); }

class TestController extends MandiController {
    public static $dir;
    public function __construct() { /* skip the login redirect: the test has no session */ }
    protected function service() { return svc(self::$dir); }
    public function crops($network) { return $this->allowedCrops($network); }
}
TestController::$dir = $dir;

$fails = 0;
// Results go straight to STDOUT so they never count as "output sent" for the controller's header() calls.
function check($name, $cond, $extra = '') { global $fails; fwrite(STDOUT, ($cond ? 'PASS ' : 'FAIL ') . $name . ($cond ? '' : "  -> $extra") . "\n"); if (!$cond) { $fails++; } }
function run($fn) { ob_start(); $fn(); $out = ob_get_clean(); return json_decode($out, true); }

// --- service: getReliableCrops ---
fresh($dir); FakeMandi::$meta = ['reliable_crops' => ['Potato', 'Onion', 'Tomato', 'Garlic']];
check('uses the list the service reports', svc($dir)->getReliableCrops() === ['Potato', 'Onion', 'Tomato', 'Garlic']);
check('second read is cached (one upstream call)', (svc($dir)->getReliableCrops() && count(FakeMandi::$calls) === 1), json_encode(FakeMandi::$calls));

fresh($dir); FakeMandi::$meta = ['reliable_crops' => ['Potato']];
check('cache-only mode with empty cache -> fallback, no network', svc($dir)->getReliableCrops(false) === MandiService::FALLBACK_CROPS && FakeMandi::$calls === []);
svc($dir)->getReliableCrops();   // fills the cache
FakeMandi::$calls = [];
check('cache-only mode reads the cached list, no network', svc($dir)->getReliableCrops(false) === ['Potato'] && FakeMandi::$calls === []);

fresh($dir); FakeMandi::$meta = 'DOWN';
check('service down, no cache -> fallback list', svc($dir)->getReliableCrops() === MandiService::FALLBACK_CROPS);

fresh($dir); FakeMandi::$meta = ['reliable_crops' => ['Potato', 'Garlic']]; svc($dir)->getReliableCrops();
foreach (glob($dir . '/*.json') as $f) { $r = json_decode(file_get_contents($f), true); $r['saved_at'] = 1; file_put_contents($f, json_encode($r)); }  // make it old
FakeMandi::$meta = 'DOWN';
check('service down, old cache -> old list still used', svc($dir)->getReliableCrops() === ['Potato', 'Garlic']);

// --- service: cleanCropList ---
check('clean: wrong types -> empty', MandiService::cleanCropList('Potato') === [] && MandiService::cleanCropList(null) === [] && MandiService::cleanCropList(5) === []);
check('clean: drops non-strings, blanks, duplicates', MandiService::cleanCropList(['Potato', 5, null, '  ', 'Potato', ' Onion ', ['x']]) === ['Potato', 'Onion']);
check('clean: drops markup / control characters', MandiService::cleanCropList(['<script>', 'Pot"ato', "Tom\x00ato", 'A&B', 'Okra']) === ['Okra']);
check('clean: drops over-long names', MandiService::cleanCropList([str_repeat('a', 61), 'Potato']) === ['Potato']);
check('clean: absurdly long list -> rejected', MandiService::cleanCropList(array_map(function ($i) { return "Crop$i"; }, range(1, 51))) === []);
fresh($dir); FakeMandi::$meta = ['reliable_crops' => []];
check('empty list from service -> fallback', svc($dir)->getReliableCrops() === MandiService::FALLBACK_CROPS);
fresh($dir); FakeMandi::$meta = ['reliable_crops' => 'oops'];
check('garbage list from service -> fallback', svc($dir)->getReliableCrops() === MandiService::FALLBACK_CROPS);

// --- controller ---
$c = new TestController();
fresh($dir); FakeMandi::$meta = ['reliable_crops' => ['Potato', 'Onion', 'Tomato']];
$_GET = ['crop' => 'Wheat'];
$r = run(function () use ($c) { $c->mandis(); });
check('mandis: unsupported crop -> 400 and never asked upstream for its mandis', $r['ok'] === false && $r['error'] === 'Unsupported crop' && !in_array('/meta?crop=Wheat', FakeMandi::$calls), json_encode(FakeMandi::$calls));
$_GET = ['crop' => 'Potato'];
$r = run(function () use ($c) { $c->mandis(); });
check('mandis: supported crop returns mandis', $r['ok'] === true && $r['mandis'] === ['Rayya', 'Khanna'], json_encode($r));
$_GET = ['crop' => ''];
FakeMandi::$calls = [];
$r = run(function () use ($c) { $c->mandis(); });
check('mandis: empty crop -> 400 with no upstream call', $r['ok'] === false && FakeMandi::$calls === []);
$_GET = ['crop' => ['Potato']];
$r = run(function () use ($c) { $c->mandis(); });
check('mandis: array crop param -> 400, no crash', $r['ok'] === false);

$_GET = ['crop' => 'Onion', 'mandi' => 'Khanna'];
$r = run(function () use ($c) { $c->forecast(); });
check('forecast: supported pair works and is its own data', $r['ok'] === true && $r['data']['crop'] === 'Onion' && $r['data']['mandi'] === 'Khanna', json_encode($r));
$_GET = ['crop' => 'Potato', 'mandi' => 'Nowhere'];
$r = run(function () use ($c) { $c->forecast(); });
check('forecast: unknown mandi -> HTTP 404 with a readable message', $r['ok'] === false && http_response_code() === 404 && strpos($r['error'], 'No forecast is available') === 0, json_encode($r));
$_GET = ['crop' => 'Potato', 'mandi' => 'Broken'];
$r = run(function () use ($c) { $c->forecast(); });
check('forecast: service error (503) is still HTTP 502', $r['ok'] === false && http_response_code() === 502, json_encode($r));
$_GET = ['crop' => 'Wheat', 'mandi' => 'Khanna'];
FakeMandi::$calls = [];
$r = run(function () use ($c) { $c->forecast(); });
check('forecast: unsupported crop -> 400, /predict never called', $r['ok'] === false && !in_array('/predict?crop=Wheat&mandi=Khanna', FakeMandi::$calls));
$_GET = ['crop' => 'Potato', 'mandi' => ['x']];
$r = run(function () use ($c) { $c->forecast(); });
check('forecast: array mandi param -> 400, no crash', $r['ok'] === false);
$_GET = ['crop' => 'Potato', 'mandi' => '  '];
$r = run(function () use ($c) { $c->forecast(); });
check('forecast: blank mandi -> 400', $r['ok'] === false);

// service reports a new crop -> app accepts it without a code change
fresh($dir); FakeMandi::$meta = ['reliable_crops' => ['Potato', 'Garlic']];
$_GET = ['crop' => 'Garlic'];
$r = run(function () use ($c) { $c->mandis(); });
check('a crop added by the service is accepted (no PHP edit needed)', $r['ok'] === true);
$_GET = ['crop' => 'Tomato'];
$r = run(function () use ($c) { $c->mandis(); });
check('a crop removed by the service is refused', $r['ok'] === false && $r['error'] === 'Unsupported crop');

// service down with no cache: built-in list keeps working
fresh($dir); FakeMandi::$meta = 'DOWN';
$_GET = ['crop' => 'Potato'];
$r = run(function () use ($c) { $c->mandis(); });
check('service down + no cache: Potato passes the whitelist (then fails upstream with 502 message)', $r['ok'] === false && $r['error'] !== 'Unsupported crop', json_encode($r));
$_GET = ['crop' => 'Wheat'];
$r = run(function () use ($c) { $c->mandis(); });
check('service down + no cache: Wheat still refused', $r['ok'] === false && $r['error'] === 'Unsupported crop');

// page render path never touches the network
fresh($dir); FakeMandi::$meta = ['reliable_crops' => ['Potato', 'Onion', 'Tomato', 'Garlic']];
check('page render: empty cache -> fallback list, zero network calls', $c->crops(false) === MandiService::FALLBACK_CROPS && FakeMandi::$calls === []);
svc($dir)->getReliableCrops(); FakeMandi::$calls = [];
check('page render: uses cached list, zero network calls', $c->crops(false) === ['Potato', 'Onion', 'Tomato', 'Garlic'] && FakeMandi::$calls === []);

foreach (glob($dir . '/*') ?: [] as $f) { unlink($f); } @rmdir($dir);
fwrite(STDOUT, $fails === 0 ? "\nOK: all crop-list checks passed.\n" : "\n$fails check(s) FAILED.\n");
exit($fails === 0 ? 0 : 1);
