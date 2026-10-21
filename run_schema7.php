<?php
// One-off maintenance script: run it from the command line only (php run_schema7.php).
// Refused over HTTP so a server that exposes the project root cannot run it.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this from the command line: php run_schema7.php\n");
}
require __DIR__ . '/app/Core/Database.php';
$pdo = App\Core\Database::getInstance()->getConnection();
$sql = file_get_contents(__DIR__ . '/database/schema_phase7.sql');
try {
    $pdo->exec($sql);
    echo "Phase 7 schema executed successfully.\n";
} catch (PDOException $e) {
    echo "Error executing schema: " . $e->getMessage() . "\n";
}
