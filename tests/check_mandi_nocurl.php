<?php
// Tests that a missing PHP cURL extension gives a normal error result, not a fatal error.
// No network and no real cURL calls. Run: php tests/check_mandi_nocurl.php
require __DIR__ . '/../app/Helpers/MandiConfig.php';
require __DIR__ . '/../app/Services/MandiService.php';

use App\Services\MandiService;

$fail = 0;
function check($name, $ok, $extra = '') { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok ? '' : "  -> $extra") . "\n"; if (!$ok) { $fail++; } }

// A service that behaves as if the cURL extension is not loaded.
class NoCurl extends MandiService {
    protected function curlAvailable(): bool { return false; }
}

$tmp = sys_get_temp_dir() . '/mandi_nc_' . bin2hex(random_bytes(4));
$cfg = ['api_base' => 'http://127.0.0.1:8001', 'fallback_base' => 'https://hosted.example.com', 'cache_dir' => $tmp];

$s = new NoCurl($cfg);
$r = $s->getStatus();
check('GET without cURL: normal error, no fatal', $r['ok'] === false && $r['status'] === 0, json_encode($r));
check('error names the cURL extension', strpos((string) $r['error'], 'cURL') !== false, (string) $r['error']);

$r = $s->getPredict('Potato', 'Rayya');
check('forecast without cURL: error result', $r['ok'] === false && $r['data'] === null);

$v = $s->getVoiceAdvisory('hello', 'en');
check('voice (POST) without cURL: error result', is_array($v) && empty($v['ok']), json_encode($v));

// A cached answer is still served (marked stale) when cURL is missing.
class Warm extends MandiService {
    protected function httpGet($base, $path, $params = []) {
        return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['crop' => 'Potato']];
    }
}
$w = new Warm($cfg);
$w->getMeta('Potato');                                   // fills the cache
foreach (glob($tmp . '/*.json') ?: [] as $f) {           // make it older than the cache time
    $j = json_decode((string) file_get_contents($f), true);
    if (is_array($j)) { $j['saved_at'] = time() - 86400; file_put_contents($f, json_encode($j)); }
}
$r = (new NoCurl($cfg))->getMeta('Potato');
check('stale cached answer still served without cURL', $r['ok'] === true && $r['stale'] === true && ($r['data']['crop'] ?? '') === 'Potato', json_encode($r));

foreach (glob($tmp . '/*') ?: [] as $f) { @unlink($f); } @rmdir($tmp);
echo $fail ? "FAILED: $fail check(s)\n" : "OK: all no-cURL checks passed.\n";
exit($fail ? 1 : 0);
