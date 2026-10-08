<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class Crop {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAllMasterCrops() {
        $stmt = $this->db->query("SELECT * FROM crops WHERE status = 'active' ORDER BY name ASC");
        return $stmt->fetchAll();
    }

    public function addFarmerCrop($userId, $landId, $cropId, $sowingDate, $area) {
        // Simple logic to calculate harvest date based on duration
        $stmtCrop = $this->db->prepare("SELECT expected_duration_days FROM crops WHERE id = ?");
        $stmtCrop->execute([$cropId]);
        $crop = $stmtCrop->fetch();
        
        $harvestDate = null;
        if ($crop) {
            $harvestDate = date('Y-m-d', strtotime($sowingDate . ' + ' . $crop['expected_duration_days'] . ' days'));
        }

        $stmt = $this->db->prepare("INSERT INTO farmer_crops (user_id, land_id, crop_id, sowing_date, expected_harvest_date, area_allocated) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$userId, $landId, $cropId, $sowingDate, $harvestDate, $area]);
    }

    public function getFarmerCrops($userId) {
        $stmt = $this->db->prepare("
            SELECT fc.*, c.name as crop_name, c.name_hi as crop_name_hi, l.area as total_land_area 
            FROM farmer_crops fc 
            JOIN crops c ON fc.crop_id = c.id 
            JOIN lands l ON fc.land_id = l.id 
            WHERE fc.user_id = ? 
            ORDER BY fc.created_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
