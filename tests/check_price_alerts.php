<?php
// Tests for price alerts: rules, model (in-memory SQLite) and checker (fake service). No network or MySQL.
// Run: php tests/check_price_alerts.php
if (!extension_loaded('pdo_sqlite')) { echo "SKIP: pdo_sqlite is not installed.\n"; exit(0); }
require __DIR__ . '/../app/Helpers/PriceAlertRules.php';
require __DIR__ . '/../app/Core/Database.php';
require __DIR__ . '/../app/Models/PriceAlert.php';
require __DIR__ . '/../app/Helpers/MandiConfig.php';
require __DIR__ . '/../app/Services/MandiService.php';
require __DIR__ . '/../app/Services/PriceAlertChecker.php';

use App\Helpers\PriceAlertRules as R;
use App\Models\PriceAlert;
use App\Services\MandiService;
use App\Services\PriceAlertChecker;

$fail = 0;
function check($name, $ok, $extra = '') { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok ? '' : "  -> $extra") . "\n"; if (!$ok) { $fail++; } }

// ---- rules ----
check('above: equal counts', R::isTriggered('above', 800, 800) && R::isTriggered('above', '800.00', '850.5'));
check('above: below target does not', !R::isTriggered('above', 800, 799.99));
check('below: equal counts', R::isTriggered('below', 500, 500) && R::isTriggered('below', 500, 420));
check('below: above target does not', !R::isTriggered('below', 500, 500.01));
check('bad direction never triggers', !R::isTriggered('sideways', 1, 1000) && !R::isTriggered('', 1, 1000));
check('non-numeric or zero price never triggers', !R::isTriggered('below', 500, 'abc') && !R::isTriggered('below', 500, 0) && !R::isTriggered('below', 500, null) && !R::isTriggered('above', 'x', 5));
check('latestPoint picks newest valid', R::latestPoint([['date' => '2026-10-10', 'price' => 500], ['date' => '2026-10-18', 'price' => '540.5'], ['date' => '2026-10-12', 'price' => 900]]) === [540.5, '2026-10-18']);
check('latestPoint skips junk', R::latestPoint(['x', ['date' => 'tomorrow', 'price' => 1], ['date' => '2026-10-18', 'price' => 0], ['date' => '2026-10-17', 'price' => 600]]) === [600.0, '2026-10-17']);
check('latestPoint of nothing is null', R::latestPoint([]) === null && R::latestPoint(null) === null && R::latestPoint('x') === null);
check('canonicalMandi matches ignoring case and returns service spelling', R::canonicalMandi(' rayya ', ['Jalandhar', 'Rayya']) === 'Rayya');
check('canonicalMandi rejects unknown', R::canonicalMandi('Delhi', ['Rayya']) === null && R::canonicalMandi(['x'], ['Rayya']) === null && R::canonicalMandi('Rayya', 'x') === null);

// ---- model ----
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE price_alerts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, crop TEXT NOT NULL, mandi TEXT NOT NULL, direction TEXT NOT NULL, target_price REAL NOT NULL, status TEXT NOT NULL DEFAULT 'active', last_price REAL, last_price_date TEXT, last_checked_at TEXT, triggered_price REAL, triggered_price_date TEXT, triggered_at TEXT, seen INT NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT)");
$m = new PriceAlert($pdo);
$a1 = $m->create(1, 'Potato', 'Rayya', 'above', 800);
$a2 = $m->create(1, 'Onion', 'Khanna', 'below', 300);
$b1 = $m->create(2, 'Potato', 'Rayya', 'below', 400);
check('create returns ids', $a1 > 0 && $a2 > $a1 && $b1 > $a2);
check('countActive per user', $m->countActive(1) === 2 && $m->countActive(2) === 1);
check('exists finds identical active alert', $m->exists(1, 'Potato', 'Rayya', 'above', 800.0) && !$m->exists(1, 'Potato', 'Rayya', 'above', 801.0) && !$m->exists(2, 'Potato', 'Rayya', 'above', 800.0));
check('listByUser shows only own alerts', count($m->listByUser(1)) === 2 && count($m->listByUser(2)) === 1);
check('another user cannot delete my alert', $m->delete($a1, 2) === false && count($m->listByUser(1)) === 2);
check('another user cannot re-arm my alert', $m->rearm($a1, 2) === false);
check('listActive for one user vs everyone', count($m->listActive(1)) === 2 && count($m->listActive()) === 3);

check('markTriggered works once', $m->markTriggered($a1, 850.0, '2026-10-18') === true && $m->markTriggered($a1, 860.0, '2026-10-18') === false);
check('triggered alert leaves the active list', count($m->listActive(1)) === 1 && $m->countActive(1) === 1);
check('unseen count and ordering (triggered first)', $m->countUnseen(1) === 1 && (int) $m->listByUser(1)[0]['id'] === $a1);
check('recentTriggered', count($m->recentTriggered(1)) === 1 && (float) $m->recentTriggered(1)[0]['triggered_price'] === 850.0);
$m->markAllSeen(1);
check('markAllSeen', $m->countUnseen(1) === 0 && $m->countUnseen(2) === 0);
check('rearm puts it back to watching', $m->rearm($a1, 1) === true && $m->countActive(1) === 2 && $m->recentTriggered(1) === []);
check('owner can delete', $m->delete($a2, 1) === true && count($m->listByUser(1)) === 1);

// ---- checker ----
class FakeHist extends MandiService {
    public static $calls = [];
    public static $series = [];   // "crop|mandi" => points or null
    protected function fetchRemote($path, $params = []) {
        if ($path !== '/history') { return ['ok' => false, 'status' => 404, 'error' => 'x', 'data' => null]; }
        self::$calls[] = $params['crop'] . '|' . $params['mandi'];
        $pts = self::$series[$params['crop'] . '|' . $params['mandi']] ?? null;
        return $pts === null ? ['ok' => false, 'status' => 0, 'error' => 'down', 'data' => null]
                             : ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['points' => $pts]];
    }
}
$tmp = sys_get_temp_dir() . '/pa_' . bin2hex(random_bytes(4));
function svc($tmp) { return new FakeHist(['cache_dir' => $tmp, 'cache_ttl_seconds' => 0]); }

$pdo->exec("DELETE FROM price_alerts");
$m = new PriceAlert($pdo);
$x1 = $m->create(1, 'Potato', 'Rayya', 'above', 800);
$x2 = $m->create(2, 'Potato', 'Rayya', 'below', 400);
$x3 = $m->create(2, 'Potato', 'Rayya', 'above', 900);
$x4 = $m->create(3, 'Onion', 'Khanna', 'below', 300);
FakeHist::$series = ['Potato|Rayya' => [['date' => '2026-10-17', 'price' => 790], ['date' => '2026-10-18', 'price' => 820]], 'Onion|Khanna' => null];
$sum = (new PriceAlertChecker($m, svc($tmp)))->run();
check('one lookup per crop+mandi pair', FakeHist::$calls === ['Potato|Rayya', 'Onion|Khanna'], json_encode(FakeHist::$calls));
check('summary counts', $sum === ['checked' => 3, 'triggered' => 1, 'unavailable' => 1, 'pairs' => 2], json_encode($sum));
$rows = []; foreach ($m->listByUser(1) as $r) { $rows[$r['id']] = $r; }
check('above 800 at 820 triggered with price and date', $rows[$x1]['status'] === 'triggered' && (float) $rows[$x1]['triggered_price'] === 820.0 && $rows[$x1]['triggered_price_date'] === '2026-10-18');
$u2 = []; foreach ($m->listByUser(2) as $r) { $u2[$r['id']] = $r; }
check('untriggered alerts stay active and remember the last price', $u2[$x2]['status'] === 'active' && (float) $u2[$x2]['last_price'] === 820.0 && $u2[$x3]['status'] === 'active');
$u3 = $m->listByUser(3)[0];
check('alert with no price available is left active and untouched', $u3['status'] === 'active' && $u3['last_price'] === null);

FakeHist::$calls = [];
$sum = (new PriceAlertChecker($m, svc($tmp)))->run(2);
check('run for one user only looks at that user', FakeHist::$calls === ['Potato|Rayya'] && $sum['checked'] === 2 && $sum['triggered'] === 0, json_encode([FakeHist::$calls, $sum]));

FakeHist::$series['Potato|Rayya'] = [['date' => '2026-10-19', 'price' => 380]];
$sum = (new PriceAlertChecker($m, svc($tmp)))->run();
check('below 400 at 380 triggers on a later run', $sum['triggered'] === 1 && $m->countUnseen(2) === 1);
$again = (new PriceAlertChecker($m, svc($tmp)))->run();
check('already triggered alerts are not checked again', $again['checked'] === 1 && $again['triggered'] === 0 && $again['unavailable'] === 1, json_encode($again));

echo $fail === 0 ? "\nOK: all price alert checks passed.\n" : "\n$fail check(s) FAILED.\n";
exit($fail === 0 ? 0 : 1);
