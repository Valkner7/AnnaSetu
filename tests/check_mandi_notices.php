<?php
// Run: php tests/check_mandi_notices.php   (exit 0 = pass)
// Source check: the forecast page shows BOTH the pair's own data note and the dataset age warning,
// so a mandi whose newest record is weeks old is not described only as "3 days ago".
$fail = 0;
function check($n, $ok) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $n . "\n"; $fail += $ok ? 0 : 1; }
$src = file_get_contents(__DIR__ . '/../views/mandi/prices.php');
check('page has pickNotices (plural)', strpos($src, 'function pickNotices(') !== false);
check('old single-notice picker is gone', strpos($src, 'function pickNotice(') === false);
check('pair note is pushed', strpos($src, 'out.push(d.data_note)') !== false);
check('dataset warning is pushed when different', strpos($src, 'd.data_age_warning !== d.data_note') !== false);
check('render shows every notice', strpos($src, 'pickNotices(d, hidden).forEach') !== false);
exit($fail ? 1 : 0);
