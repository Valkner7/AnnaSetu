<?php
require __DIR__ . '/app/Core/Database.php';
$pdo = App\Core\Database::getInstance()->getConnection();

$sql = file_get_contents(__DIR__ . '/database/schema_phase4.sql');

try {
    $pdo->exec($sql);
    echo "Phase 4 schema executed successfully.\n";
} catch (PDOException $e) {
    echo "Error executing schema: " . $e->getMessage() . "\n";
}
