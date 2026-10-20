<?php
// Guards the crops.name_pa fix (Issue 1): the buyer marketplace selects crops.name_pa, so
// setup_db.php must create the column and load the Punjabi names, and the names must match
// the crops that schema_phase2.sql inserts. Offline: needs no database.
// Run: php tests/check_crops_name_pa.php
$fail = 0;
function check($name, $ok) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }

$root = __DIR__ . '/..';
$names = require $root . '/database/crops_name_pa.php';
$setup = file_get_contents($root . '/setup_db.php');
$schema = file_get_contents($root . '/database/schema_phase2.sql');

// every crop the schema inserts: ('Wheat', ...
preg_match_all("/^\('([^']+)',/m", $schema, $m);
$seeded = $m[1];

check('schema seeds 6 crops', count($seeded) === 6);
check('a Punjabi name for every seeded crop', array_diff($seeded, array_keys($names)) === []);
check('no Punjabi name for an unknown crop', array_diff(array_keys($names), $seeded) === []);
$allGurmukhi = true;
foreach ($names as $v) { if (!preg_match('/\p{Gurmukhi}/u', $v)) { $allGurmukhi = false; } }
check('every name contains Gurmukhi script', $allGurmukhi);

check('setup_db.php checks information_schema before adding the column',
    strpos($setup, 'information_schema.COLUMNS') !== false && strpos($setup, 'ADD COLUMN name_pa') !== false);
check('setup_db.php does not use ADD COLUMN IF NOT EXISTS (MySQL 8 rejects it)',
    !preg_match('/ALTER\s+TABLE[^;]*IF\s+NOT\s+EXISTS/i', $setup));
check('setup_db.php loads the shared name list', strpos($setup, 'crops_name_pa.php') !== false);
check('setup_db.php only fills empty names', strpos($setup, 'name_pa IS NULL') !== false);
check('fix_crops_pa.php uses the shared list', strpos(file_get_contents($root . '/fix_crops_pa.php'), 'crops_name_pa.php') !== false);

exit($fail ? 1 : 0);
