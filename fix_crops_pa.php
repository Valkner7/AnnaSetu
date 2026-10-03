<?php
require __DIR__ . '/app/Core/Database.php';
$pdo = App\Core\Database::getInstance()->getConnection();

$pdo->exec("SET NAMES utf8mb4;");

$updates = [
    'Wheat' => 'ਕਣਕ (Kanak)',
    'Rice (Paddy)' => 'ਝੋਨਾ (Jhona)',
    'Tomato' => 'ਟਮਾਟਰ (Tamatar)',
    'Onion' => 'ਪਿਆਜ਼ (Pyaz)',
    'Potato' => 'ਆਲੂ (Aloo)',
    'Sugarcane' => 'ਗੰਨਾ (Ganna)'
];

$stmt = $pdo->prepare("UPDATE crops SET name_pa = ? WHERE name = ?");

foreach ($updates as $name => $pa) {
    $stmt->execute([$pa, $name]);
}

echo "Punjabi translations added to crops.\n";
