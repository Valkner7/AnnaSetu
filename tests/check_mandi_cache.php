<?php
// Tests for the MandiService file cache (M4). No network needed:
// a subclass replaces the HTTP call with a scripted fake.
// Run: php tests/check_mandi_cache.php
require __DIR__ . '/../app/Services/MandiService.php';

use App\Services\MandiService;

class FakeMandi extends MandiService {
    public $calls = 0;
    public $script = [];   // list of responses returned in order; last one repeats
    protected function fetchRemote($path, $params = []) {
        $this->calls++;
        $i = min($this->calls - 1, count($this->script) - 1);
        $r = $this->script[$i];
        // Echo the params back so tests can prove no cross-key mix-up.
        if ($r['ok']) { $r['data'] = $r['data'] + ['_asked' => $params]; }
        return $r;
    }
}

$fails = 0;
function check($name, $cond) {
    global $fails;
    echo ($cond ? 'PASS ' : 'FAIL ') . $name . "\n";
    if (!$cond) { $fails++; }
}
function okR($d)  { return ['ok' => true, 'status' => 200, 'error' => null, 'data' => $d]; }
function errR($s) { return ['ok' => false, 'status' => $s, 'error' => 'e' . $s, 'data' => null]; }
function rmrf($d) { foreach (glob($d . '/*') ?: [] as $f) { is_dir($f) ? rmrf($f) : unlink($f); } @rmdir($d); }

$dir = sys_get_temp_dir() . '/mandi_cache_test_' . bin2hex(random_bytes(4));
$mk = function ($ttl) use ($dir) {
    return new FakeMandi(['cache_dir' => $dir, 'cache_ttl_seconds' => $ttl, 'api_base' => 'http://x']);
};

// 1. Miss then hit: second call does not touch the network.
$s = $mk(3600); $s->script = [okR(['latest_price' => 582])];
$a = $s->getPredict('Potato', 'Rayya');
$b = $s->getPredict('Potato', 'Rayya');
check('first call goes upstream', $s->calls === 1 && $a['ok'] && !$a['cached']);
check('second call served from cache', $s->calls === 1 && $b['ok'] && $b['cached'] && !$b['stale']);
check('cached data matches', $b['data']['latest_price'] === 582);

// 2. Different crop+mandi never shares an entry (R5 safety).
$s->script = [okR(['latest_price' => 3000])];
$c = $s->getPredict('Onion', 'Khanna');
check('different pair is a separate entry', $c['ok'] && !$c['cached'] && $c['data']['latest_price'] === 3000);
$d = $s->getPredict('Potato', 'Rayya');
check('first pair still its own data', $d['data']['latest_price'] === 582);

// 3. Meta with and without crop are different keys.
$s->script = [okR(['mandis' => ['A']])];
$m1 = $s->getMeta('Potato'); $m2 = $s->getMeta();
check('meta with/without crop are separate', !$m1['cached'] && !$m2['cached']);

// 4. Expired entry refetches (TTL 0 means always stale).
$z = $mk(0); $z->script = [okR(['latest_price' => 1]), okR(['latest_price' => 2])];
$z->getPredict('Tomato', 'Rayya');
$r = $z->getPredict('Tomato', 'Rayya');
check('expired entry refetches', $z->calls === 2 && $r['data']['latest_price'] === 2 && !$r['cached']);

// 5. Outage with an old copy: serve stale.
$z->script = [errR(0)];
$z->calls = 0;
$o = $z->getPredict('Tomato', 'Rayya');
check('timeout serves stale copy', $o['ok'] && $o['cached'] && $o['stale'] && $o['data']['latest_price'] === 2);
$z->script = [errR(503)];
$o = $z->getPredict('Tomato', 'Rayya');
check('503 serves stale copy', $o['ok'] && $o['stale']);

// 6. Real "no forecast" answers are not hidden by an old copy.
$z->script = [errR(404)];
$o = $z->getPredict('Tomato', 'Rayya');
check('404 is returned, not masked', !$o['ok'] && $o['status'] === 404);

// 7. Outage with no copy: error comes through, nothing cached.
$n = $mk(3600); $n->script = [errR(0)];
$o = $n->getPredict('Onion', 'Rayya');
check('outage with no copy returns the error', !$o['ok'] && $o['status'] === 0);
$dir2 = $dir . '_errs';
$e = new FakeMandi(['cache_dir' => $dir2, 'cache_ttl_seconds' => 3600, 'api_base' => 'http://x']);
$e->script = [errR(500)];
$e->getPredict('Onion', 'Rayya');
check('errors are not cached', count(glob($dir2 . '/*.json') ?: []) === 0);
rmrf($dir2);

// 8. Corrupt cache file is ignored, not fatal.
foreach (glob($dir . '/*.json') as $f) { file_put_contents($f, '{not json'); }
$k = $mk(3600); $k->script = [okR(['latest_price' => 9])];
$o = $k->getPredict('Potato', 'Rayya');
check('corrupt cache file is ignored', $o['ok'] && !$o['cached'] && $o['data']['latest_price'] === 9);

// 9. No leftover temp files.
check('no .tmp files left behind', count(glob($dir . '/*.tmp')) === 0);

rmrf($dir);
echo $fails === 0 ? "\nOK: all cache checks passed.\n" : "\n$fails check(s) FAILED.\n";
exit($fails === 0 ? 0 : 1);
