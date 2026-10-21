<?php
// Database check for setup_db.php. Needs a real MySQL/MariaDB server, so it lives outside the
// offline tests/check_*.php set and runs in the "database-setup" CI job.
//
// Run, from the project folder, with config/database.php pointing at an empty server:
//     php setup_db.php && php setup_db.php && php tests/db/check_setup_db.php
// It creates smartharvest_db contents and then DAMAGES them on purpose to test the upgrade path,
// so use a throw-away database only.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Run from the command line.\n"); }

$root = dirname(__DIR__, 2);
$config = require "$root/config/database.php";
$fail = 0;
function check($name, $ok, $extra = '') { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . ($ok ? '' : "  -> $extra") . "\n"; $fail += $ok ? 0 : 1; }

$pdo = new PDO("mysql:host={$config['host']};port=" . ($config['port'] ?? '3306') . ";dbname=smartharvest_db;charset=utf8mb4",
    $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$one = function ($sql, $args = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($args); return $s->fetchColumn(); };
$hasKey = fn() => (int) $one("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA='smartharvest_db' AND TABLE_NAME='crops' AND INDEX_NAME='uniq_crop_name'") > 0;

// 1. Every table named in a schema file exists (the list comes from the files, so it stays current).
$expected = [];
foreach (glob("$root/database/schema_phase*.sql") as $f) {
    preg_match_all('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?/i', file_get_contents($f), $m);
    $expected = array_merge($expected, $m[1]);
}
$have = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
$missing = array_values(array_diff($expected, $have));
check('all ' . count($expected) . ' tables from the schema files exist', $expected && !$missing, 'missing: ' . implode(', ', $missing));

// 2. State after setup_db.php has been run more than once.
check('crops has exactly the 6 default rows (re-runs did not double them)', (int) $one('SELECT COUNT(*) FROM crops') === 6, (string) $one('SELECT COUNT(*) FROM crops'));
check('crop names are unique', (int) $one('SELECT COUNT(DISTINCT name) FROM crops') === 6);
check('unique key uniq_crop_name exists', $hasKey());
check('crops.name_pa column exists', (int) $one("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='smartharvest_db' AND TABLE_NAME='crops' AND COLUMN_NAME='name_pa'") === 1);
check('every crop has a Punjabi name', (int) $one("SELECT COUNT(*) FROM crops WHERE name_pa IS NULL OR name_pa = ''") === 0);
check('the buyer marketplace query works (it reads crops.name_pa)', is_array($pdo->query('SELECT id, name, name_hi, name_pa FROM crops')->fetchAll()));

// 3. A re-run must not overwrite a Punjabi name someone edited by hand.
$pdo->exec("UPDATE crops SET name_pa = 'EDITED' WHERE name = 'Wheat'");
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/setup_db.php") . ' > /dev/null 2>&1', $code);
check('setup_db.php re-run succeeds', $code === 0, "exit $code");
check('a hand-edited name_pa survives a re-run', $one("SELECT name_pa FROM crops WHERE name = 'Wheat'") === 'EDITED');

// 4. Upgrade path: a database built by the OLD script has duplicate crops, no unique key, and
//    farmer records pointing at the extra rows. Rebuild that state, then run the new script.
$pdo->exec("INSERT INTO users (first_name, last_name, phone, password_hash) VALUES ('Test', 'Farmer', '9000000001', 'x')");
$uid = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO lands (user_id, area, soil_type) VALUES ($uid, 2.5, 'loam')");
$landId = (int) $pdo->lastInsertId();
$pdo->exec('ALTER TABLE crops DROP INDEX uniq_crop_name');
$pdo->exec("INSERT INTO crops (name, name_hi, expected_duration_days, water_requirement) SELECT name, name_hi, expected_duration_days, water_requirement FROM crops");   // 12 rows
$keepId = (int) $one("SELECT MIN(id) FROM crops WHERE name = 'Tomato'");
$dupId = (int) $one("SELECT MAX(id) FROM crops WHERE name = 'Tomato'");
$pdo->exec("INSERT INTO farmer_crops (user_id, land_id, crop_id, sowing_date, area_allocated) VALUES ($uid, $landId, $dupId, '2026-10-01', 1.0)");
check('test setup: 12 crop rows, no unique key, a farmer crop on a duplicate row', (int) $one('SELECT COUNT(*) FROM crops') === 12 && !$hasKey() && $keepId !== $dupId);

passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$root/setup_db.php") . ' > /dev/null 2>&1', $code);
check('setup_db.php succeeds on the damaged database', $code === 0, "exit $code");
check('duplicates merged back to 6 crops', (int) $one('SELECT COUNT(*) FROM crops') === 6, (string) $one('SELECT COUNT(*) FROM crops'));
check('unique key is back', $hasKey());
check('the farmer\'s crop record was kept and moved to the surviving row', (int) $one('SELECT COUNT(*) FROM farmer_crops WHERE user_id = ?', [$uid]) === 1 && (int) $one('SELECT crop_id FROM farmer_crops WHERE user_id = ?', [$uid]) === $keepId);

// 5. Clean up the rows this check created.
$pdo->exec("DELETE FROM farmer_crops WHERE user_id = $uid");
$pdo->exec("DELETE FROM users WHERE id = $uid");   // lands cascade
exit($fail ? 1 : 0);
