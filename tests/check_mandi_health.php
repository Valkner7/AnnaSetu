<?php
// Tests for the service health summary and the health endpoint. No network.
// Run: php tests/check_mandi_health.php
define('BASE_URL', '/app');
require __DIR__ . '/../app/Helpers/Translator.php';
require __DIR__ . '/../app/Helpers/MandiText.php';
require __DIR__ . '/../app/Helpers/MandiHealth.php';
require __DIR__ . '/../app/Services/MandiService.php';
require __DIR__ . '/../app/Controllers/MandiController.php';

use App\Helpers\MandiHealth;
use App\Controllers\MandiController;
use App\Services\MandiService;

ob_start();
$log = ''; $fail = 0;
function check($name, $ok) { global $fail, $log; $log .= ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }

$good = ['status' => 'ok', 'reasons' => [], 'data' => ['latest_date' => '2026-10-18', 'days_stale' => 0], 'model' => ['artifact_loaded' => true]];
$r = MandiHealth::summarize($good, 'local');
check('ok', $r['level'] === 'ok' && $r['source'] === 'local' && $r['latest_date'] === '2026-10-18' && $r['days_stale'] === 0 && $r['model_loaded'] === true);

$stale = $good; $stale['status'] = 'degraded'; $stale['reasons'] = ['data_stale']; $stale['data']['days_stale'] = 24;
check('only data_stale -> stale', MandiHealth::summarize($stale)['level'] === 'stale');

$turso = $stale; $turso['reasons'] = ['subscriptions_db_init_failed', 'data_stale'];
check('Turso reason is ignored', MandiHealth::summarize($turso)['level'] === 'stale');
$turso['reasons'] = ['subscriptions_db_init_failed'];
check('Turso reason alone -> ok', MandiHealth::summarize($turso)['level'] === 'ok');

$bad = $good; $bad['reasons'] = ['model_selftest_failed', 'data_stale'];
check('other reasons -> degraded', MandiHealth::summarize($bad)['level'] === 'degraded');

check('no answer -> down', MandiHealth::summarize(null)['level'] === 'down');
check('junk answer -> down', MandiHealth::summarize(['hello' => 1])['level'] === 'down');
check('hosted source kept', MandiHealth::summarize($good, 'hosted')['source'] === 'hosted');
check('unknown source becomes local', MandiHealth::summarize($good, 'evil<script>')['source'] === 'local');
$odd = $good; $odd['data'] = ['latest_date' => '<b>x</b>', 'days_stale' => 'many'];
$o = MandiHealth::summarize($odd);
check('odd data values are dropped', $o['latest_date'] === null && $o['days_stale'] === null);
$odd['reasons'] = 'nope';
check('non-list reasons treated as none', MandiHealth::summarize($odd)['level'] === 'ok');

class FakeHealth extends MandiService {
    public static $res;
    protected function fetchRemote($path, $params = []) { return self::$res; }
}
class HealthCtl extends MandiController {
    public function __construct() {}
    protected function service() { return new FakeHealth(['cache_dir' => sys_get_temp_dir() . '/mh']); }
}
FakeHealth::$res = ['ok' => true, 'status' => 200, 'error' => null, 'data' => $stale, 'via' => 'local'];
ob_start(); (new HealthCtl())->health(); $out = json_decode(ob_get_clean(), true);
check('endpoint returns summary only', $out['ok'] === true && $out['data']['level'] === 'stale' && !isset($out['data']['runtime']) && !isset($out['data']['usage']));
FakeHealth::$res = ['ok' => false, 'status' => 0, 'error' => 'down', 'data' => null, 'via' => 'hosted'];
ob_start(); (new HealthCtl())->health(); $out = json_decode(ob_get_clean(), true);
check('endpoint when nothing answers', $out['ok'] === false && $out['data']['level'] === 'down');

ob_end_clean();
fwrite(STDOUT, $log . ($fail === 0 ? "\nOK: all health checks passed.\n" : "\n$fail check(s) FAILED.\n"));
exit($fail === 0 ? 0 : 1);
