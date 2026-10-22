<?php
// The shared secret (X-Api-Secret) must reach the configured primary price service
// and never the fallback address. Runs two real local servers: a primary that always
// answers 500 (so the call falls back) and a fallback that answers 200, and records
// which headers each one actually received. Run: php tests/check_mandi_secret_hosts.php
require __DIR__ . '/../app/Services/MandiService.php';

use App\Services\MandiService;

$fails = 0;
function check($name, $cond, $extra = '') { global $fails; fwrite(STDOUT, ($cond ? 'PASS ' : 'FAIL ') . $name . ($cond ? '' : "  -> $extra") . "\n"); if (!$cond) { $fails++; } }

if (!function_exists('curl_init')) {
    fwrite(STDOUT, "FAIL the PHP cURL extension is required for this check\n");
    exit(1);
}

$tmp = sys_get_temp_dir() . '/mandi_secret_hosts_' . bin2hex(random_bytes(4));
mkdir($tmp);

// Router script: records the secret header it received, then answers with $status.
function writeRouter($tmp, $name, $status) {
    $log = $tmp . '/' . $name . '.log';
    $php = '<?php $h = $_SERVER["HTTP_X_API_SECRET"] ?? "(none)";'
         . 'file_put_contents(' . var_export($log, true) . ', $_SERVER["REQUEST_URI"] . " " . $h . "\n", FILE_APPEND);'
         . 'http_response_code(' . (int) $status . ');'
         . 'header("Content-Type: application/json"); echo "{\"advisory\":\"ok\"}";';
    $file = $tmp . '/' . $name . '.php';
    file_put_contents($file, $php);
    return [$file, $log];
}

function startServer($router, $port) {
    $cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", $router];
    $proc = proc_open($cmd, [1 => ['file', sys_get_temp_dir() . '/mandi_srv.out', 'a'], 2 => ['file', sys_get_temp_dir() . '/mandi_srv.out', 'a']], $pipes);
    for ($i = 0; $i < 50; $i++) {
        $s = @fsockopen('127.0.0.1', $port, $e, $m, 0.2);
        if ($s) { fclose($s); return $proc; }
        usleep(100000);
    }
    return null;
}

$p1 = random_int(20000, 30000);
$p2 = $p1 + 1;
[$primaryRouter, $primaryLog]   = writeRouter($tmp, 'primary', 500);
[$fallbackRouter, $fallbackLog] = writeRouter($tmp, 'fallback', 200);
$srv1 = startServer($primaryRouter, $p1);
$srv2 = startServer($fallbackRouter, $p2);
check('test servers started', $srv1 && $srv2);

$SECRET = 'abcd1234efgh5678';
$svc = new MandiService([
    'api_base'      => "http://127.0.0.1:$p1",
    'fallback_base' => "http://127.0.0.1:$p2",
    'api_secret'    => $SECRET,
    'cache_dir'     => $tmp . '/cache',
    'cache_ttl_seconds' => 0,
]);

function lines($f) { return is_file($f) ? array_values(array_filter(explode("\n", file_get_contents($f)))) : []; }

if ($srv1 && $srv2) {
    // JSON advisory: primary answers 500, so the call falls back.
    $svc->getAdvisory('Potato', 'Rayya', 'price?', 'en');
    $pl = lines($primaryLog); $fl = lines($fallbackLog);
    check('advisory: primary was called with the secret', count($pl) === 1 && substr($pl[0], -strlen($SECRET)) === $SECRET, json_encode($pl));
    check('advisory: fallback WAS called', count($fl) === 1, json_encode($fl));
    check('advisory: fallback did NOT receive the secret', count($fl) === 1 && substr($fl[0], -6) === '(none)', json_encode($fl));

    // Spoken advisory takes a different code path (POST).
    @unlink($primaryLog); @unlink($fallbackLog);
    $svc->getVoiceAdvisory('price?', 'en');
    $pl = lines($primaryLog); $fl = lines($fallbackLog);
    check('voice: primary was called with the secret', count($pl) === 1 && substr($pl[0], -strlen($SECRET)) === $SECRET, json_encode($pl));
    check('voice: fallback did NOT receive the secret', count($fl) === 1 && substr($fl[0], -6) === '(none)', json_encode($fl));
}

// Header rules, without a network.
class HeaderProbe extends MandiService {
    public function headers($path, $base = null) { return $this->requestHeaders($path, $base); }
}
$probe = new HeaderProbe(['api_base' => 'http://127.0.0.1:8001/', 'fallback_base' => 'https://hosted.example.org', 'api_secret' => $SECRET, 'cache_dir' => $tmp . '/cache']);
$with = ['Accept: application/json', "X-Api-Secret: $SECRET"];
$none = ['Accept: application/json'];
check('primary address gets the secret', $probe->headers('/advisory', 'http://127.0.0.1:8001') === $with);
check('primary matches case-insensitively, ignoring a trailing slash', $probe->headers('/advisory', 'HTTP://127.0.0.1:8001/') === $with);
check('fallback address never gets the secret', $probe->headers('/advisory', 'https://hosted.example.org') === $none);
check('a lookalike address never gets the secret', $probe->headers('/advisory', 'http://127.0.0.1:80010') === $none);
check('no base given = primary (older callers keep working)', $probe->headers('/advisory') === $with);
check('other paths never get the secret, even on the primary', $probe->headers('/meta', 'http://127.0.0.1:8001') === $none);

// Issue 22: the hosted fallback has its own optional secret, sent only to it.
$FB = 'fallback-secret-9999';
$probe2 = new HeaderProbe(['api_base' => 'http://127.0.0.1:8001', 'fallback_base' => 'https://hosted.example.org', 'api_secret' => $SECRET, 'fallback_secret' => $FB, 'cache_dir' => $tmp . '/cache']);
check('fallback host gets its own secret', $probe2->headers('/advisory', 'https://hosted.example.org') === ['Accept: application/json', "X-Api-Secret: $FB"]);
check('primary host never gets the fallback secret', $probe2->headers('/advisory', 'http://127.0.0.1:8001') === $with);
check('an unrelated host gets neither secret', $probe2->headers('/advisory', 'https://evil.example.com') === $none);
check('fallback secret not sent on other paths', $probe2->headers('/meta', 'https://hosted.example.org') === $none);
check('no fallback secret configured: fallback gets nothing', $probe->headers('/advisory', 'https://hosted.example.org') === $none);

// End to end: primary 500s, fallback answers; each server sees only its own secret.
if ($srv1 && $srv2) {
    @unlink($primaryLog); @unlink($fallbackLog);
    $svc2 = new MandiService([
        'api_base' => "http://127.0.0.1:$p1", 'fallback_base' => "http://127.0.0.1:$p2",
        'api_secret' => $SECRET, 'fallback_secret' => $FB,
        'cache_dir' => $tmp . '/cache', 'cache_ttl_seconds' => 0,
    ]);
    $svc2->getAdvisory('Potato', 'Rayya', 'price?', 'en');
    $pl = lines($primaryLog); $fl = lines($fallbackLog);
    check('e2e: primary received the primary secret only', count($pl) === 1 && substr($pl[0], -strlen($SECRET)) === $SECRET, json_encode($pl));
    check('e2e: fallback received the fallback secret only', count($fl) === 1 && substr($fl[0], -strlen($FB)) === $FB && strpos($fl[0], $SECRET) === false, json_encode($fl));
}

foreach ([$srv1 ?? null, $srv2 ?? null] as $s) { if (is_resource($s)) { proc_terminate($s); proc_close($s); } }
foreach (glob($tmp . '/*') ?: [] as $x) { is_file($x) && @unlink($x); }
@rmdir($tmp . '/cache'); @rmdir($tmp);

fwrite(STDOUT, $fails === 0 ? "\nOK: each secret only goes to its own host.\n" : "\n$fails check(s) FAILED.\n");
exit($fails === 0 ? 0 : 1);
