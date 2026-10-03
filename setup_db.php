<?php
$config = require __DIR__ . '/config/database.php';
try {
    $port = isset($config['port']) ? $config['port'] : '3306';
    $pdo = new PDO("mysql:host={$config['host']};port={$port}", $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create DB
    $pdo->exec("CREATE DATABASE IF NOT EXISTS smartharvest_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database created successfully.\n";
    
    // Run schema
    $pdo->exec("USE smartharvest_db");
    $sql = file_get_contents(__DIR__ . '/database/schema_phase1.sql');
    $pdo->exec($sql);
    echo "Schema imported successfully.\n";
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
