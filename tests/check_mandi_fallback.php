<?php
// Tests for local-first service address with hosted fallback. No network: httpGet is faked.
// Run: php tests/check_mandi_fallback.php
require __DIR__ . '/../app/Helpers/MandiConfig.php';
require __DIR__ . '/../app/Services/MandiService.php';

use App\Services\MandiService;

$fail = 0;
function check($name, $ok, $extra = '') { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok ? '' : "  -> $extra") . "\n"; if (!$ok) { $fail++; } }

class FakeHttp extends MandiService {
    public $calls = [];
    public $answers = [];   // base => result
    protected function httpGet($base, $path, $params = []) {
        $this->calls[] = $base . $path;
        return $this->answers[$base] ?? ['ok' => false, 'status' => 0, 'error' => 'refused', 'data' => null];
    }
}
$L = 'http://127.0.0.1:8001';
$H = 'https://hosted.example.com';
$ok = ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['x' => 1]];
$tmp = sys_get_temp_dir() . '/mandi_fb_' . bin2hex(random_bytes(4));
$cfg = ['api_base' => $L, 'fallback_base' => $H, 'cache_dir' => $tmp];

$s = new FakeHttp($cfg); $s->answers[$L] = $ok;
$r = $s->getStatus();
check('local answers: no fallback call', $s->calls === [$L . '/status'] && $r['ok'] && $r['via'] === 'local' && $s->lastSource() === 'local', json_encode($s->calls));

$s = new FakeHttp($cfg); $s->answers[$H] = $ok;
$r = $s->getStatus();
check('local refused: hosted is tried and used', $s->calls === [$L . '/status', $H . '/status'] && $r['ok'] && $r['via'] === 'hosted' && $s->lastSource() === 'hosted', json_encode($s->calls));

$s = new FakeHttp($cfg); $s->answers[$L] = ['ok' => false, 'status' => 503, 'error' => 'starting', 'data' => null]; $s->answers[$H] = $ok;
$r = $s->getStatus();
check('local 5xx: hosted is tried', $r['ok'] && $r['via'] === 'hosted');

$s = new FakeHttp($cfg); $s->answers[$L] = ['ok' => false, 'status' => 404, 'error' => 'none', 'data' => null]; $s->answers[$H] = $ok;
$r = $s->getStatus();
check('local 404 is a real answer: no fallback', !$r['ok'] && $r['status'] === 404 && count($s->calls) === 1 && $r['via'] === 'local');

$s = new FakeHttp($cfg);
$r = $s->getStatus();
check('both down: error returned, 2 calls', !$r['ok'] && $r['status'] === 0 && count($s->calls) === 2);

$s = new FakeHttp(['api_base' => $L, 'fallback_base' => '', 'cache_dir' => $tmp]); $s->answers[$H] = $ok;
$r = $s->getStatus();
check('fallback off: only local is tried', !$r['ok'] && count($s->calls) === 1);

$s = new FakeHttp(['api_base' => $H, 'fallback_base' => $H, 'cache_dir' => $tmp]);
$s->getStatus();
check('same address twice is not retried', count($s->calls) === 1);

$s = new FakeHttp($cfg); $s->answers[$H] = $ok;
$a = $s->getMeta('Potato'); $b = $s->getMeta('Potato');
check('cached answers do not call the service again', count($s->calls) === 2 && $b['cached'] === true, json_encode($s->calls));

check('isLoopback', MandiService::isLoopback('http://localhost:1') && MandiService::isLoopback('http://[::1]:8') && !MandiService::isLoopback('https://example.com') && !MandiService::isLoopback(null));

foreach (glob($tmp . '/*') ?: [] as $f) { @unlink($f); } @rmdir($tmp);
echo $fail === 0 ? "\nOK: all fallback checks passed.\n" : "\n$fail check(s) FAILED.\n";
exit($fail === 0 ? 0 : 1);
