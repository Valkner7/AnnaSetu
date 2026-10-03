<?php
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
