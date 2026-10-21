<?php
// Guards the "setup_db.php doubles the crops on every re-run" fix: crops had no unique key on
// name, so INSERT IGNORE in schema_phase2.sql never skipped anything. Offline: needs no database
// (the behaviour itself was tested against MariaDB when the fix was written).
// Run: php tests/check_crops_unique_name.php
$fail = 0;
function check($name, $ok) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }

$root = __DIR__ . '/..';
$schema = file_get_contents($root . '/database/schema_phase2.sql');
$setup  = file_get_contents($root . '/setup_db.php');

preg_match('/CREATE TABLE IF NOT EXISTS crops \((.*?)\n\);/s', $schema, $m);
check('schema_phase2.sql defines the crops table', !empty($m[1]));
check('crops has UNIQUE KEY uniq_crop_name (name) for fresh installs',
    !empty($m[1]) && preg_match('/UNIQUE KEY uniq_crop_name \(name\)/', $m[1]) === 1);

check('setup_db.php checks information_schema.STATISTICS before adding the key',
    strpos($setup, 'information_schema.STATISTICS') !== false && strpos($setup, 'ADD UNIQUE KEY uniq_crop_name') !== false);
check('setup_db.php points farmer_crops at the kept row before deleting (FK is RESTRICT)',
    preg_match('/UPDATE farmer_crops.*DELETE d FROM crops/s', $setup) === 1);
check('setup_db.php keeps the oldest row of each name', strpos($setup, 'MIN(id)') !== false);
check('setup_db.php does the cleanup in a transaction with rollback',
    strpos($setup, 'beginTransaction') !== false && strpos($setup, 'rollBack') !== false);
check('the key is added after the cleanup, not before',
    strpos($setup, 'DELETE d FROM crops') < strpos($setup, 'ADD UNIQUE KEY uniq_crop_name'));

exit($fail ? 1 : 0);
