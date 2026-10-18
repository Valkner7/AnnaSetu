<?php
// Tests for the Market Prices page translations (M3).
// No network, no database. Run: php tests/check_mandi_i18n.php

define('BASE_URL', '/app');
require __DIR__ . '/../app/Helpers/Translator.php';
use App\Helpers\Translator;

// Same helpers the app defines in public/index.php.
function __($key) { return Translator::get($key); }
function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

$fail = 0;
function check($name, $ok) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }

$lang = [];
foreach (['en', 'hi', 'pa'] as $l) { $lang[$l] = require __DIR__ . "/../lang/$l.php"; }
$mp = array_values(array_filter(array_keys($lang['en']), fn($k) => strpos($k, 'mp_') === 0));
check('English has mandi keys (' . count($mp) . ')', count($mp) >= 20);

foreach (['hi', 'pa'] as $l) {
    $missing = array_diff($mp, array_keys($lang[$l]));
    check("$l has every mp_ key", !$missing);
    $bad = [];
    foreach ($mp as $k) {
        preg_match_all('/\{(\w+)\}/', $lang['en'][$k], $a);
        preg_match_all('/\{(\w+)\}/', $lang[$l][$k] ?? '', $b);
        sort($a[1]); sort($b[1]);
        if ($a[1] !== $b[1]) { $bad[] = $k; }
        if (trim($lang[$l][$k] ?? '') === '') { $bad[] = $k . ' (empty)'; }
    }
    check("$l placeholders match English", !$bad);
}

// A key missing from a language shows English, not the raw key.
Translator::load('en');
$english = Translator::get('mp_show_prices');
$tmp = __DIR__ . '/../lang/zz_test.php';
file_put_contents($tmp, "<?php return ['app_name' => 'x'];");
Translator::load('zz_test');
check('missing key falls back to English', Translator::get('mp_show_prices') === $english);
check('translated key still wins', Translator::get('app_name') === 'x');
unlink($tmp);
Translator::load('xx_unknown');
check('unknown language falls back to English', Translator::get('mp_show_prices') === $english);

// Render the real view in each language.
$crops = ['Potato', 'Onion'];
foreach (['en', 'hi', 'pa'] as $l) {
    Translator::load($l);
    ob_start();
    require __DIR__ . '/../views/mandi/prices.php';
    $html = ob_get_clean();
    check("$l view renders", strlen($html) > 1500);
    check("$l view shows no raw mp_ key", !preg_match('/mp_[a-z_]+/', preg_replace('/<script.*?<\/script>/s', '', $html)));
    check("$l view has the translated title", strpos($html, e($lang[$l]['mp_title'])) !== false);
    check("$l view lists the crops", strpos($html, '>Potato<') !== false);
    // The strings handed to JS must round-trip as valid JSON.
    preg_match('/var T = (\{.*?\});\n/s', $html, $m);
    $t = json_decode($m[1] ?? '', true);
    check("$l JS strings are valid JSON", is_array($t) && count($t) === 17);
    foreach (["chart_history", "chart_forecast", "chart_note", "unit_label"] as $k) { check("$l JS has $k", !empty($t[$k])); }
    check("$l view has the chart canvas", strpos($html, "id=\"priceChart\"") !== false && strpos($html, "assets/js/mandi-chart.js") !== false);
}

// No hard-coded English left in the visible markup or JS messages.
$src = file_get_contents(__DIR__ . '/../views/mandi/prices.php');
foreach (['Show prices', 'Last reported price', 'Choose crop', 'Loading prices', 'No current projection', 'Projected price'] as $s) {
    check("view has no hard-coded '$s'", strpos($src, $s) === false);
}

echo $fail ? "\nFAILED: $fail check(s)\n" : "\nOK: all i18n checks passed.\n";
exit($fail ? 1 : 0);
