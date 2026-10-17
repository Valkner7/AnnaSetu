<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class Land {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function addLand($userId, $area, $soilType, $irrigationType, $location) {
        $stmt = $this->db->prepare("INSERT INTO lands (user_id, area, soil_type, irrigation_type, location_address) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $area, $soilType, $irrigationType, $location]);
    }

    // True only if this land exists and belongs to this user (N3).
    public function belongsToUser($landId, $userId): bool {
        $landId = filter_var($landId, FILTER_VALIDATE_INT);
        if ($landId === false || $landId < 1) {
            return false;
        }
        $stmt = $this->db->prepare("SELECT 1 FROM lands WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$landId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    public function getLandsByUser($userId) {
        $stmt = $this->db->prepare("SELECT * FROM lands WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
