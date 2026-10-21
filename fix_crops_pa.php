<?php
// One-off maintenance script: run it from the command line only (php fix_crops_pa.php).
// Refused over HTTP so a server that exposes the project root cannot run it.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this from the command line: php fix_crops_pa.php\n");
}
// Re-applies the Punjabi crop names. `php setup_db.php` now does this
// automatically (and creates the name_pa column), so this is only needed to
// overwrite names. Unlike setup_db.php it replaces existing values.
require __DIR__ . '/app/Core/Database.php';
$pdo = App\Core\Database::getInstance()->getConnection();

$pdo->exec("SET NAMES utf8mb4;");

$updates = require __DIR__ . '/database/crops_name_pa.php';

$stmt = $pdo->prepare("UPDATE crops SET name_pa = ? WHERE name = ?");

foreach ($updates as $name => $pa) {
    $stmt->execute([$pa, $name]);
}

echo "Punjabi translations added to crops.\n";
