<?php
// Tests for the dashboard "Market Prices" card: alert counts, the missing-table case, and the rendered partial in en/hi/pa.
// No network or MySQL. Run: php tests/check_market_card.php
if (!extension_loaded('pdo_sqlite')) { echo "SKIP: pdo_sqlite is not installed.\n"; exit(0); }
define('BASE_URL', '/app');
require __DIR__ . '/../app/Helpers/Translator.php';
require __DIR__ . '/../app/Helpers/MandiText.php';
require __DIR__ . '/../app/Core/Database.php';
require __DIR__ . '/../app/Models/PriceAlert.php';
require __DIR__ . '/../app/Helpers/MarketCard.php';

use App\Helpers\MarketCard;
use App\Helpers\Translator;
use App\Models\PriceAlert;

function __($k) { return Translator::get($k); }
function e($v): string { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
Translator::load('en');

$fail = 0;
function check($name, $ok, $extra = '') { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok ? '' : "  -> $extra") . "\n"; if (!$ok) { $fail++; } }
$t = function ($k) { return __($k); };

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$empty = new PriceAlert($pdo);   // no table yet

$s = MarketCard::summary($empty, 1);
check('missing table: card still works, alerts unavailable', $s['available'] === false && $s['unseen'] === 0 && $s['active'] === 0 && $s['recent'] === []);
check('missing table: no alert line', MarketCard::alertLine($s, $t) === '');
$s = MarketCard::summary(null, 1);
check('no model: unavailable', $s['available'] === false);

$pdo->exec("CREATE TABLE price_alerts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INT NOT NULL, crop TEXT NOT NULL, mandi TEXT NOT NULL, direction TEXT NOT NULL, target_price REAL NOT NULL, status TEXT NOT NULL DEFAULT 'active', last_price REAL, last_price_date TEXT, last_checked_at TEXT, triggered_price REAL, triggered_price_date TEXT, triggered_at TEXT, seen INT NOT NULL DEFAULT 0, created_at TEXT, updated_at TEXT)");
$alerts = new PriceAlert($pdo);

$s = MarketCard::summary($alerts, 1);
check('no alerts: available, zero counts', $s['available'] === true && $s['unseen'] === 0 && $s['active'] === 0);
check('no alerts: says so', MarketCard::alertLine($s, $t) === __('dm_no_alerts'));

$alerts->create(1, 'Potato', 'Rayya', 'above', 500);
$alerts->create(1, 'Onion', 'Khanna', 'below', 100);
$alerts->create(2, 'Potato', 'Rayya', 'above', 50);   // another user's alert must not count
$s = MarketCard::summary($alerts, 1);
check('active alerts counted for this user only', $s['active'] === 2 && $s['unseen'] === 0, json_encode($s));
check('watching line has the number', MarketCard::alertLine($s, $t) === 'Watching 2 price alert(s).', MarketCard::alertLine($s, $t));

$id = (int) $pdo->query("SELECT id FROM price_alerts WHERE user_id = 1 AND crop = 'Potato'")->fetchColumn();
$alerts->markTriggered($id, 725, '2026-10-18');
$s = MarketCard::summary($alerts, 1);
check('triggered alert is unseen, active drops', $s['unseen'] === 1 && $s['active'] === 1 && count($s['recent']) === 1, json_encode($s));
check('reached line wins over watching', MarketCard::alertLine($s, $t) === '1 price alert(s) reached their price.', MarketCard::alertLine($s, $t));
check('other user sees nothing reached', MarketCard::summary($alerts, 2)['unseen'] === 0);

foreach (['en', 'hi', 'pa'] as $lang) {
    $l = $lang;
    Translator::load($lang);
    $marketCard = MarketCard::summary($alerts, 1);
    ob_start();
    require __DIR__ . '/../views/dashboard/_market_card.php';
    $html = ob_get_clean();
    $plain = preg_replace('/<script.*?<\/script>/s', '', $html);
    check("$l card renders", strlen($html) > 800);
    check("$l card shows the translated title", strpos($html, e(__('dm_title'))) !== false);
    check("$l card has no raw dm_/mn_/mh_ key", !preg_match('/\b(dm|mn|mh)_[a-z_]+/', $plain));
    check("$l card links to prices, compare, ask and alerts", strpos($html, 'url=mandi"') !== false && strpos($html, 'url=mandi/compare"') !== false && strpos($html, 'url=advisory"') !== false && strpos($html, 'url=alerts"') !== false);
    check("$l card has the health badge and its endpoint", strpos($html, 'id="market-health"') !== false && (strpos($html, 'mandi/health') !== false || strpos($html, 'mandi\\/health') !== false));
    check("$l card shows no unfilled {n}", strpos($plain, '{n}') === false);
    preg_match('/var T = (\{.*?\});\r?\n/s', $html, $m);
    $js = json_decode($m[1] ?? '', true);
    check("$l health strings are valid JSON with 6 texts", is_array($js) && count($js) === 6 && !in_array('', $js, true));
}

// A user-controlled alert text must never reach the page unescaped (the card prints only counts, never crop or mandi names).
Translator::load('en');
$alerts->create(1, '<script>alert(1)</script>', 'X', 'above', 1);
$marketCard = MarketCard::summary($alerts, 1);
ob_start(); require __DIR__ . '/../views/dashboard/_market_card.php'; $html = ob_get_clean();
check('alert names are not printed on the card', strpos($html, '<script>alert(1)') === false);

echo $fail ? "\nFAILED: $fail check(s)\n" : "\nOK: all market card checks passed.\n";
exit($fail ? 1 : 0);
