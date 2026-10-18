<?php
// Tests for the English/Hindi/Punjabi text of the forecast (MandiText).
// No network, no database. Run: php tests/check_mandi_text.php
define('BASE_URL', '');
require __DIR__ . '/../app/Helpers/Translator.php';
require __DIR__ . '/../app/Helpers/MandiText.php';
require __DIR__ . '/../app/Services/MandiService.php';
require __DIR__ . '/../app/Controllers/MandiController.php';

use App\Helpers\MandiText;
use App\Helpers\Translator;
use App\Controllers\MandiController;
use App\Services\MandiService;

$fails = 0;
function check($name, $cond, $extra = '') { global $fails; fwrite(STDOUT, ($cond ? 'PASS ' : 'FAIL ') . $name . ($cond ? '' : "  -> $extra") . "\n"); if (!$cond) { $fails++; } }
function tr($lang) { Translator::load($lang); return function ($k) { return Translator::get($k); }; }

$svc = [
    'crop' => 'Potato', 'mandi' => 'Rayya', 'latest_date' => '2026-09-24', 'latest_price' => 582.0,
    'trend' => 'rising', 'confidence_note' => 'ENGLISH FROM SERVICE',
    'data_age_warning' => 'English warning from service', 'data_note' => 'English note from service',
    'data_age_days' => 23, 'dataset_latest_date' => '2026-09-24',
];

// --- helpers ---
check('slug', MandiText::slug('Bhindi(Ladies Finger)') === 'bhindi_ladies_finger' && MandiText::slug('Potato') === 'potato');
check('fill replaces known, keeps unknown', MandiText::fill('{a} x {b}', ['a' => 1]) === '1 x {b}');
check('daysSince counts whole days', MandiText::daysSince('2026-09-24', '2026-10-17') === 23);
check('daysSince never negative', MandiText::daysSince('2026-12-01', '2026-10-17') === 0);
check('daysSince rejects junk', MandiText::daysSince('yesterday') === null && MandiText::daysSince(null) === null);

// --- crop labels ---
check('en crop label is plain', MandiText::cropLabel('Potato', tr('en')) === 'Potato');
check('hi crop label has Hindi + English', MandiText::cropLabel('Potato', tr('hi')) === 'आलू (Potato)');
check('pa crop label has Punjabi + English', MandiText::cropLabel('Onion', tr('pa')) === 'ਪਿਆਜ਼ (Onion)');
check('unknown crop keeps its name', MandiText::cropLabel('Dragonfruit', tr('hi')) === 'Dragonfruit');

// --- English keeps the service's wording ---
$en = MandiText::localize($svc, 'en', tr('en'), '2026-10-17');
check('en keeps service confidence note', $en['confidence_note'] === 'ENGLISH FROM SERVICE');
check('en keeps service notices', $en['data_age_warning'] === 'English warning from service' && $en['data_note'] === 'English note from service');
check('en trend label', $en['trend_label'] === 'rising');

// --- Hindi and Punjabi ---
foreach (['hi', 'pa'] as $l) {
    $r = MandiText::localize($svc, $l, tr($l), '2026-10-17');
    check("$l trend is translated", $r['trend_label'] !== 'rising' && $r['trend_label'] !== '' && strpos($r['trend_label'], 'mp_') === false);
    check("$l confidence note is translated", $r['confidence_note'] !== 'ENGLISH FROM SERVICE' && !preg_match('/[A-Za-z]{4,}/', $r['confidence_note']), $r['confidence_note']);
    check("$l age warning has the date and days", strpos($r['data_age_warning'], '2026-09-24') !== false && strpos($r['data_age_warning'], '23') !== false && strpos($r['data_age_warning'], '{') === false, $r['data_age_warning']);
    check("$l data note has the date and computed days", strpos($r['data_note'], '2026-09-24') !== false && strpos($r['data_note'], '23') !== false && strpos($r['data_note'], '{') === false, $r['data_note']);
    check("$l notices contain no English words", !preg_match('/[A-Za-z]{3,}/', preg_replace('/\d{4}-\d{2}-\d{2}/', '', $r['data_age_warning'] . $r['data_note'])));
}

// --- no invented warnings ---
$quiet = $svc; unset($quiet['data_age_warning'], $quiet['data_note']);
$r = MandiText::localize($quiet, 'hi', tr('hi'), '2026-10-17');
check('no notice from the service means none is added', empty($r['data_age_warning']) && empty($r['data_note']));
// --- missing numbers: keep the service text rather than guess ---
$noNum = $svc; unset($noNum['data_age_days'], $noNum['dataset_latest_date']); $noNum['latest_date'] = 'garbage';
$r = MandiText::localize($noNum, 'pa', tr('pa'), '2026-10-17');
check('missing numbers keep the service warning', $r['data_age_warning'] === 'English warning from service');
check('bad latest_date keeps the service note', $r['data_note'] === 'English note from service');
// --- unknown trend does not break ---
$odd = $svc; $odd['trend'] = 'sideways';
check('unknown trend falls back to the raw word', MandiText::localize($odd, 'hi', tr('hi'))['trend_label'] === 'sideways');
$none = $svc; unset($none['trend']);
check('missing trend gives empty label', MandiText::localize($none, 'hi', tr('hi'))['trend_label'] === '');

// --- faithful meaning: the HI/PA confidence notes must not sound surer than English ---
$hi = require __DIR__ . '/../lang/hi.php';
check('hi confidence note says "only sometimes" (केवल कभी-कभी)', strpos($hi['mp_confidence_note'], 'केवल कभी-कभी') !== false);
$pa = require __DIR__ . '/../lang/pa.php';
check('pa confidence note says "only sometimes" (ਸਿਰਫ਼ ਕਦੇ-ਕਦੇ)', strpos($pa['mp_confidence_note'], 'ਸਿਰਫ਼ ਕਦੇ-ਕਦੇ') !== false);

// --- the real controller returns Hindi when the session language is Hindi ---
class FakeSvc extends MandiService {
    protected function fetchRemote($path, $params = []) {
        if ($path === '/meta' && !$params) { return ['ok' => true, 'status' => 200, 'error' => null, 'data' => ['reliable_crops' => ['Potato']]]; }
        return ['ok' => true, 'status' => 200, 'error' => null, 'data' => [
            'crop' => 'Potato', 'mandi' => 'Rayya', 'latest_date' => '2026-09-24', 'latest_price' => 582, 'trend' => 'stable',
            'forecast' => [], 'confidence' => ['note' => 'EN note'], 'data_age_warning' => 'EN warn', 'data_age_days' => 23,
            'dataset_latest_date' => '2026-09-24']];
    }
}
class Ctl extends MandiController {
    public static $dir;
    public function __construct() {}
    protected function service() { return new FakeSvc(['cache_dir' => self::$dir, 'api_base' => 'http://x', 'cache_ttl_seconds' => 0]); }
}
Ctl::$dir = sys_get_temp_dir() . '/mandi_text_' . bin2hex(random_bytes(4));
foreach (['en' => 'EN warn', 'hi' => null, 'pa' => null] as $l => $expectWarn) {
    Translator::load($l);
    $_GET = ['crop' => 'Potato', 'mandi' => 'Rayya'];
    ob_start(); (new Ctl())->forecast(); $out = json_decode(ob_get_clean(), true);
    $w = $out['data']['data_age_warning'] ?? '';
    if ($l === 'en') {
        check('controller en: service warning unchanged', $w === 'EN warn');
    } else {
        check("controller $l: warning translated with numbers", $w !== 'EN warn' && strpos($w, '23') !== false, $w);
        check("controller $l: trend and confidence translated", $out['data']['trend_label'] !== 'stable' && $out['data']['confidence_note'] !== 'EN note');
    }
}
foreach (glob(Ctl::$dir . '/*') ?: [] as $f) { unlink($f); } @rmdir(Ctl::$dir);
exit($fails ? 1 : 0);
