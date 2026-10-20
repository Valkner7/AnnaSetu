<?php
// The price service's Hindi and Punjabi confidence notes (FORECAST_CONFIDENCE_NOTE in
// ai/mandi_service/routers/predict.py) must say the same thing as AnnaSetu's own
// 'mp_confidence_note' in lang/hi.php and lang/pa.php. The service text is what shows
// in the spoken/written advisory, AnnaSetu's is what the forecast card shows, and both
// must be equally honest: forecasts beat "no change" only sometimes.
// Run: php tests/check_confidence_notes.php
$fails = 0;
function check($name, $cond, $extra = '') { global $fails; fwrite(STDOUT, ($cond ? 'PASS ' : 'FAIL ') . $name . ($cond ? '' : "  -> $extra") . "\n"); if (!$cond) { $fails++; } }

$py = file_get_contents(__DIR__ . '/../ai/mandi_service/routers/predict.py');
check('read the service source', is_string($py) && $py !== '');

// Pull the dict out and join the adjacent string literals of one language.
function serviceNote($py, $lang) {
    if (!preg_match('/FORECAST_CONFIDENCE_NOTE\s*=\s*\{(.*?)\n\}/su', $py, $m)) { return null; }
    if (!preg_match('/"' . $lang . '":\s*((?:"[^"\n]*"\s*)+),/u', $m[1], $n)) { return null; }
    preg_match_all('/"([^"\n]*)"/u', $n[1], $parts);
    return implode('', $parts[1]);
}

foreach (['hi', 'pa'] as $lang) {
    $svc = serviceNote($py, $lang);
    $lng = require __DIR__ . "/../lang/$lang.php";
    $app = $lng['mp_confidence_note'] ?? null;
    check("$lang: found the service note", is_string($svc) && $svc !== '');
    check("$lang: found AnnaSetu's mp_confidence_note", is_string($app) && $app !== '');
    check("$lang: service note equals AnnaSetu's note", $svc === $app, json_encode([$svc, $app], JSON_UNESCAPED_UNICODE));
}

$hiOnly = 'केवल कभी-कभी';
$paOnly = 'ਸਿਰਫ਼ ਕਦੇ-ਕਦੇ';
check('hi service note says "only sometimes"', strpos((string) serviceNote($py, 'hi'), $hiOnly) !== false);
check('pa service note says "only sometimes"', strpos((string) serviceNote($py, 'pa'), $paOnly) !== false);

fwrite(STDOUT, $fails === 0 ? "\nOK: service and app confidence notes agree.\n" : "\n$fails check(s) FAILED.\n");
exit($fails === 0 ? 0 : 1);
