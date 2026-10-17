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

    // True only for an active crop in the master list (N3).
    public function masterCropExists($cropId): bool {
        $cropId = filter_var($cropId, FILTER_VALIDATE_INT);
        if ($cropId === false || $cropId < 1) {
            return false;
        }
        $stmt = $this->db->prepare("SELECT 1 FROM crops WHERE id = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$cropId]);
        return (bool)$stmt->fetchColumn();
    }

    public function addFarmerCrop($userId, $landId, $cropId, $sowingDate, $area) {
        // The land must belong to this user and the crop must exist (N3).
        $own = $this->db->prepare("SELECT 1 FROM lands WHERE id = ? AND user_id = ? LIMIT 1");
        $own->execute([$landId, $userId]);
        if (!$own->fetchColumn() || !$this->masterCropExists($cropId)) {
            return false;
        }

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
