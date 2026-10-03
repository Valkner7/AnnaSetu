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

    public function getLandsByUser($userId) {
        $stmt = $this->db->prepare("SELECT * FROM lands WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
