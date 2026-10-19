<?php
// When the price service cannot answer, visitors get a translated, friendly message and never
// the technical detail (address, port, cURL hint). No network: a fake service fails on purpose.
// Run: php tests/check_mandi_errors.php
define('BASE_URL', '/app');
require __DIR__ . '/../app/Helpers/Translator.php';
require __DIR__ . '/../app/Helpers/MandiText.php';
require __DIR__ . '/../app/Services/MandiService.php';
require __DIR__ . '/../app/Controllers/MandiController.php';

use App\Controllers\MandiController;
use App\Helpers\Translator;
use App\Services\MandiService;

ini_set('error_log', sys_get_temp_dir() . '/mandi_errors_test.log');   // keep the expected log lines out of the output
ob_start();
$log = ''; $fail = 0;
function check($name, $ok) { global $fail, $log; $log .= ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }

const DETAIL = 'Could not reach the price service (Failed to connect to 127.0.0.1 port 8001 after 0 ms)';
class FakeDown extends MandiService {
    protected function fetchRemote($path, $params = []) {
        if ($path === '/meta' && !isset($params['crop'])) {
            return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['reliable_crops' => ['Potato']]];
        }
        return ['ok' => false, 'status' => 0, 'error' => DETAIL, 'data' => null];
    }
}
class TestDown extends MandiController {
    public static $dir;
    public function __construct() {}
    protected function service() { return new FakeDown(['cache_dir' => self::$dir, 'api_base' => 'http://x', 'cache_ttl_seconds' => 3600]); }
}
TestDown::$dir = sys_get_temp_dir() . '/mandi_err_test_' . bin2hex(random_bytes(4));
function call($method, $get, $lang) {
    foreach (glob(TestDown::$dir . '/*') ?: [] as $f) { unlink($f); }
    Translator::load($lang);
    $_GET = $get;
    ob_start(); (new TestDown())->$method(); $out = ob_get_clean();
    return [json_decode($out, true), http_response_code()];
}

foreach (['en', 'hi', 'pa'] as $lang) {
    $want = Translator::get('al_err_service');          // reloaded below for each language
    Translator::load($lang);
    $want = Translator::get('al_err_service');
    $cases = [
        'mandis'   => ['mandis',   ['crop' => 'Potato']],
        'forecast' => ['forecast', ['crop' => 'Potato', 'mandi' => 'Rayya']],
        'compare'  => ['comparison', ['crop' => 'Potato', 'mandis' => 'Rayya,Khanna']],
    ];
    foreach ($cases as $label => $c) {
        list($r, $code) = call($c[0], $c[1], $lang);
        $err = (string) ($r['error'] ?? '');
        check("$lang $label: friendly translated message", $r['ok'] === false && $err === $want && $err !== 'al_err_service');
        check("$lang $label: no address, port or cURL detail shown", strpos($err, '127.0.0.1') === false && strpos($err, 'cURL') === false && strpos($err, 'Failed to connect') === false);
    }
}
Translator::load('en');
check('hi message differs from English (really translated)', (function () { Translator::load('hi'); $h = Translator::get('al_err_service'); Translator::load('en'); return $h !== Translator::get('al_err_service'); })());
check('the detail is written to the server log', strpos((string) @file_get_contents(sys_get_temp_dir() . '/mandi_errors_test.log'), '127.0.0.1 port 8001') !== false);

ob_end_clean();
echo $log;
echo $fail ? "\nFAILED: $fail\n" : "\nOK: all service-error checks passed.\n";
exit($fail ? 1 : 0);
