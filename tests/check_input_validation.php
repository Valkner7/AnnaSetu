<?php
// Guards the input checks that stop raw database errors: values the database cannot hold must
// be refused with a clear message instead of a blank HTTP 500 or a silent rounding.
// Offline: reads source files (the behaviour was tested end to end against MariaDB when written).
// Run: php tests/check_input_validation.php
$fail = 0;
function check($name, $ok) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }
$root = __DIR__ . '/..';
$eq   = file_get_contents($root . '/app/Controllers/EquipmentController.php');
$prof = file_get_contents($root . '/app/Controllers/ProfileController.php');
$auth = file_get_contents($root . '/app/Controllers/AuthController.php');

// equipment type: controller list == database ENUM == dropdown on the fleet page
preg_match("/EQUIPMENT_TYPES = \[(.*?)\];/s", $eq, $c);
preg_match_all("/'([^']+)'/", $c[1] ?? '', $cm);
$controller = $cm[1];
preg_match("/CREATE TABLE IF NOT EXISTS equipments.*?type ENUM\((.*?)\)/s", file_get_contents($root . '/database/schema_phase4.sql'), $e);
preg_match_all("/'([^']+)'/", $e[1] ?? '', $em);
preg_match_all('/<option value="([^"]+)"/', file_get_contents($root . '/views/equipment/fleet.php'), $om);
check('controller lists 5 equipment types', count($controller) === 5);
check('controller types == database ENUM', $controller === $em[1]);
check('controller types == fleet page dropdown', $controller === $om[1]);
check('addEquipment uses oneOf on that list', strpos($eq, 'Validator::oneOf($_POST[\'type\'] ?? null, self::EQUIPMENT_TYPES)') !== false);

check('booking hours must be whole (INT column)', strpos($eq, "Validator::wholeNumber(\$data['duration_hours'], 1, 720)") !== false);
check('booking no longer accepts half hours', strpos($eq, "'duration_hours'], 0.5") === false);

check('buyer profile limits: company 255, gstin 50', strpos($prof, "Validator::text(\$data['company_name'], 255)") !== false && strpos($prof, "Validator::text(\$data['gstin'], 50)") !== false);
check('buyer capacity must be a whole number', strpos($prof, "Validator::wholeNumber(\$data['procurement_capacity']") !== false);
check('buyer save no longer reads delivery_address unchecked', strpos($prof, "\$data['delivery_address'], \$_SESSION") === false);
check('owner profile limits: names 255, radius whole 1-2000',
    strpos($prof, "Validator::text(\$data['business_name'], 255)") !== false && strpos($prof, "Validator::wholeNumber(\$data['service_radius_km'], 1, 2000)") !== false);
check('profile saves accept only a JSON object', substr_count($prof, '!is_array($data)') >= 2);
check('registration language limited to en/hi/pa', strpos($auth, "in_array(\$data['language'] ?? null, ['en', 'hi', 'pa'], true)") !== false);

exit($fail ? 1 : 0);
