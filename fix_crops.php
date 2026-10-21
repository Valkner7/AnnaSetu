<?php
// DESTRUCTIVE one-off script: empties the crops table (foreign-key checks off) and re-inserts six
// crops with fixed ids. Any farmer_crops rows pointing at the old ids are left dangling and every
// name_pa value is lost. A normal install does not need it: setup_db.php creates the crops.
// Run it from the command line only, and only with the flag below:  php fix_crops.php --wipe-crops
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this from the command line: php fix_crops.php --wipe-crops\n");
}
if (!in_array('--wipe-crops', $argv ?? [], true)) {
    fwrite(STDERR, "fix_crops.php deletes ALL rows in the crops table and re-creates six default crops.\n"
        . "It also erases crops.name_pa and breaks farmers' crop records.\n"
        . "Nothing was changed. To go ahead anyway, run: php fix_crops.php --wipe-crops\n");
    exit(1);
}
require __DIR__ . '/app/Core/Database.php';
$pdo = App\Core\Database::getInstance()->getConnection();

$pdo->exec("SET FOREIGN_KEY_CHECKS=0;");
$pdo->exec("TRUNCATE TABLE crops;");
$pdo->exec("SET FOREIGN_KEY_CHECKS=1;");
$pdo->exec("SET NAMES utf8mb4;");

$sql = "INSERT INTO crops (id, name, name_hi, expected_duration_days, water_requirement) VALUES 
(1, 'Wheat', 'गेहूं (Gehu)', 120, 'medium'),
(2, 'Rice (Paddy)', 'धान (Dhaan)', 150, 'high'),
(3, 'Tomato', 'टमाटर (Tamatar)', 90, 'medium'),
(4, 'Onion', 'प्याज (Pyaz)', 110, 'low'),
(5, 'Potato', 'आलू (Aloo)', 100, 'medium'),
(6, 'Sugarcane', 'गन्ना (Ganna)', 300, 'high');";

$pdo->exec($sql);
echo "Fixed crops encoding.\n";
