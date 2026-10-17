<?php
// Tests for the price-service settings loader (M8). Run: php tests/check_mandi_config.php
require __DIR__ . '/../app/Helpers/MandiConfig.php';

use App\Helpers\MandiConfig;

$fails = 0;
function check($name, $cond, $extra = '') { global $fails; fwrite(STDOUT, ($cond ? 'PASS ' : 'FAIL ') . $name . ($cond ? '' : "  -> $extra") . "\n"); if (!$cond) { $fails++; } }

// Keep expected log lines out of the test output.
ini_set('error_log', sys_get_temp_dir() . '/mandi_config_test.log');

$tmp = sys_get_temp_dir() . '/mandi_cfg_' . bin2hex(random_bytes(4));
mkdir($tmp);
function localFile($tmp, $php) { $f = $tmp . '/mandi.local.php'; file_put_contents($f, $php); return $f; }
$none = $tmp . '/does-not-exist.php';
$D = MandiConfig::DEFAULT_API_BASE;

// --- defaults ---
$c = MandiConfig::load($none);
check('defaults: same address and numbers as before', $c['api_base'] === $D && $c['timeout_seconds'] === 90 && $c['connect_timeout_seconds'] === 20 && $c['cache_ttl_seconds'] === 14400, json_encode($c));
check('default address is the live host', $D === 'https://mandi-price-forecast-1.onrender.com');
check('empty env value is ignored', MandiConfig::load($none, '')['api_base'] === $D && MandiConfig::load($none, '   ')['api_base'] === $D && MandiConfig::load($none, null)['api_base'] === $D);

// --- env var ---
check('env var overrides default', MandiConfig::load($none, 'https://example.com')['api_base'] === 'https://example.com');
check('trailing slash removed', MandiConfig::load($none, 'https://example.com/')['api_base'] === 'https://example.com');
check('path prefix allowed', MandiConfig::load($none, 'https://example.com/mandi/')['api_base'] === 'https://example.com/mandi');

// --- local file ---
$f = localFile($tmp, "<?php return ['api_base' => 'https://local.example.org', 'timeout_seconds' => 30, 'cache_ttl_seconds' => 600];");
$c = MandiConfig::load($f);
check('local file overrides address and numbers', $c['api_base'] === 'https://local.example.org' && $c['timeout_seconds'] === 30 && $c['cache_ttl_seconds'] === 600 && $c['connect_timeout_seconds'] === 20, json_encode($c));
check('env var beats local file', MandiConfig::load($f, 'https://env.example.net')['api_base'] === 'https://env.example.net');
check('bad env var falls back to local file, not default', MandiConfig::load($f, 'ftp://nope')['api_base'] === 'https://local.example.org');

$f2 = localFile($tmp, "<?php return ['api_base' => 'javascript:alert(1)'];");
check('bad api_base in local file -> default', MandiConfig::load($f2)['api_base'] === $D);
$f3 = localFile($tmp, "<?php return ['evil_key' => 1, 'timeout_seconds' => 'abc', 'connect_timeout_seconds' => -5];");
$c = MandiConfig::load($f3);
check('unknown keys and bad numbers are ignored', !isset($c['evil_key']) && $c['timeout_seconds'] === 90 && $c['connect_timeout_seconds'] === 20, json_encode($c));
$f4 = localFile($tmp, "<?php return 'not an array';");
check('local file that returns junk -> defaults', MandiConfig::load($f4)['api_base'] === $D);

// --- address safety ---
$bad = ['', '   ', 'example.com', '//example.com', 'ftp://example.com', 'file:///etc/passwd', 'javascript:alert(1)',
        'http://example.com', 'https://', 'https://user:pw@example.com', 'https://example.com?x=1', 'https://example.com#frag',
        'https://exa mple.com', "https://example.com\r\nX: y", 'https://example.com/"', "https://example.com/'", 'https://example.com/<x>',
        'https://example.com\\evil', null, 5, ['https://example.com']];
foreach ($bad as $i => $b) {
    check('rejects unsafe address #' . $i . ' ' . json_encode($b), MandiConfig::cleanBase($b) === null, var_export(MandiConfig::cleanBase($b), true));
}
check('http allowed for localhost', MandiConfig::cleanBase('http://localhost:8000') === 'http://localhost:8000');
check('http allowed for 127.0.0.1', MandiConfig::cleanBase('http://127.0.0.1:8765/') === 'http://127.0.0.1:8765');
check('http allowed for ::1', MandiConfig::cleanBase('http://[::1]:8000') === 'http://[::1]:8000');
check('http NOT allowed for a public host', MandiConfig::cleanBase('http://mandi.example.com') === null);
check('uppercase scheme/host accepted', MandiConfig::cleanBase('HTTPS://Example.COM/') === 'HTTPS://Example.COM');

// --- real config file keeps working with the service class ---
require __DIR__ . '/../app/Services/MandiService.php';
$svc = new \App\Services\MandiService(['cache_dir' => $tmp . '/cache']);
$r = new ReflectionProperty($svc, 'base'); $r->setAccessible(true);
check('MandiService reads the address from config/mandi.php', $r->getValue($svc) === $D, $r->getValue($svc));

foreach (glob($tmp . '/*') as $x) { is_file($x) && unlink($x); } @rmdir($tmp);
fwrite(STDOUT, $fails === 0 ? "\nOK: all config checks passed.\n" : "\n$fails check(s) FAILED.\n");
exit($fails === 0 ? 0 : 1);
