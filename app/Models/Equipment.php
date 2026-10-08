<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class Equipment {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getEquipmentsByOwner($ownerId) {
        $stmt = $this->db->prepare("SELECT * FROM equipments WHERE owner_id = ? ORDER BY id DESC");
        $stmt->execute([$ownerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllActiveEquipments() {
        $stmt = $this->db->prepare("
            SELECT e.*, p.business_name, p.base_location, p.service_radius_km 
            FROM equipments e
            JOIN equipment_owner_profiles p ON e.owner_id = p.user_id
            WHERE e.status = 'active'
            ORDER BY e.id DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
