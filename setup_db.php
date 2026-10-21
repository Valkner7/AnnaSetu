<?php
/**
 * One-step database setup for AnnaSetu.
 *
 * Creates the database (if missing) and applies EVERY schema file in
 * database/ in order (schema_phase1.sql ... schema_phase7.sql). All phases use
 * CREATE TABLE IF NOT EXISTS / INSERT IGNORE, so it is safe to run again after
 * pulling new code: existing tables and data are left alone.
 *
 * Usage (from the project folder):
 *     php setup_db.php
 *
 * Command line only. This file is not meant to be opened in a browser.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Run this from the command line: php setup_db.php\n");
}

const EXPECTED_DB = 'smartharvest_db'; // the schema files hard-code this name

$config = require __DIR__ . '/config/database.php';

if (isset($config['dbname']) && $config['dbname'] !== EXPECTED_DB) {
    fwrite(STDERR, "config/database.php uses dbname '{$config['dbname']}' but the schema files create '" . EXPECTED_DB . "'.\n"
        . "Set 'dbname' to '" . EXPECTED_DB . "' (or edit the USE line in every database/schema_phase*.sql).\n");
    exit(1);
}

$files = glob(__DIR__ . '/database/schema_phase*.sql');
// Natural sort so phase10 would come after phase9, not after phase1.
usort($files, 'strnatcmp');
if (!$files) {
    fwrite(STDERR, "No database/schema_phase*.sql files found.\n");
    exit(1);
}

try {
    $port = $config['port'] ?? '3306';
    $pdo = new PDO("mysql:host={$config['host']};port={$port};charset=utf8mb4", $config['username'], $config['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE DATABASE IF NOT EXISTS ' . EXPECTED_DB . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE ' . EXPECTED_DB);
    echo "Database " . EXPECTED_DB . " ready.\n";

    foreach ($files as $file) {
        $name = basename($file);
        $sql = file_get_contents($file);
        try {
            // query() + nextRowset() makes PDO report an error from ANY statement in the
            // file; a plain exec() can silently skip a failure in a later statement.
            $stmt = $pdo->query($sql);
            do { } while ($stmt->nextRowset());
            $stmt->closeCursor();
        } catch (PDOException $e) {
            fwrite(STDERR, "FAILED in $name: " . $e->getMessage() . "\n");
            exit(1);
        }
        echo "Applied $name\n";
    }

    // crops.name_pa (Punjabi crop names) is read by the buyer marketplace and profile
    // pages but is not in any schema file. MySQL 8 has no ADD COLUMN IF NOT EXISTS
    // (MariaDB does), so check information_schema first. Safe to run repeatedly.
    $has = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $has->execute([EXPECTED_DB, 'crops', 'name_pa']);
    if ((int)$has->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE crops ADD COLUMN name_pa VARCHAR(100) DEFAULT NULL AFTER name_hi');
        echo "Added crops.name_pa.\n";
    }
    // Fill only empty values so a re-run never overwrites names edited by hand.
    $fill = $pdo->prepare("UPDATE crops SET name_pa = ? WHERE name = ? AND (name_pa IS NULL OR name_pa = '')");
    foreach (require __DIR__ . '/database/crops_name_pa.php' as $en => $pa) {
        $fill->execute([$pa, $en]);
    }
    echo "Punjabi crop names loaded.\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo count($files) . " schema files applied. Tables (" . count($tables) . "): " . implode(', ', $tables) . "\n";
    echo "Setup complete.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(1);
}
