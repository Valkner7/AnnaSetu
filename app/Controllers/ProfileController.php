<?php
namespace App\Controllers;

use App\Models\Land;
use App\Models\Crop;

class ProfileController {
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header("Location: /smartharvest/public/index.php?url=auth/loginView");
            exit;
        }
    }

    public function index() {
        $userRole = $_SESSION['user_role'] ?? 'farmer';
        $db = \App\Core\Database::getInstance()->getConnection();
        
        if ($userRole === 'farmer') {
            $landModel = new Land();
            $cropModel = new Crop();
            $lands = $landModel->getLandsByUser($_SESSION['user_id']);
            $masterCrops = $cropModel->getAllMasterCrops();
            $myCrops = $cropModel->getFarmerCrops($_SESSION['user_id']);
        } elseif ($userRole === 'equipment_owner') {
            $stmt = $db->prepare("SELECT * FROM equipment_owner_profiles WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $ownerProfile = $stmt->fetch();
        } elseif ($userRole === 'buyer') {
            $stmt = $db->prepare("SELECT * FROM buyer_profiles WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $buyerProfile = $stmt->fetch();
        }
        
        $title = "Profile - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/profile/index.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    public function saveBuyerProfile() {
        header('Content-Type: application/json');
        if ($_SESSION['user_role'] !== 'buyer') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['company_name'])) {
            echo json_encode(['success' => false, 'message' => 'Company Name is required.']);
            return;
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT user_id FROM buyer_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $update = $db->prepare("UPDATE buyer_profiles SET company_name=?, gstin=?, procurement_capacity=?, delivery_address=? WHERE user_id=?");
            $success = $update->execute([$data['company_name'], $data['gstin'], $data['procurement_capacity'] ?? 0, $data['delivery_address'], $_SESSION['user_id']]);
        } else {
            $insert = $db->prepare("INSERT INTO buyer_profiles (user_id, company_name, gstin, procurement_capacity, delivery_address) VALUES (?, ?, ?, ?, ?)");
            $success = $insert->execute([$_SESSION['user_id'], $data['company_name'], $data['gstin'], $data['procurement_capacity'] ?? 0, $data['delivery_address']]);
        }

        echo json_encode(['success' => $success, 'message' => $success ? 'Company Profile saved successfully!' : 'Database error.']);
    }

    public function saveOwnerProfile() {
        header('Content-Type: application/json');
        if ($_SESSION['user_role'] !== 'equipment_owner') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['business_name']) || empty($data['base_location'])) {
            echo json_encode(['success' => false, 'message' => 'Business Name and Base Location are required.']);
            return;
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT user_id FROM equipment_owner_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $update = $db->prepare("UPDATE equipment_owner_profiles SET business_name=?, service_radius_km=?, base_location=? WHERE user_id=?");
            $success = $update->execute([$data['business_name'], $data['service_radius_km'] ?? 50, $data['base_location'], $_SESSION['user_id']]);
        } else {
            $insert = $db->prepare("INSERT INTO equipment_owner_profiles (user_id, business_name, service_radius_km, base_location) VALUES (?, ?, ?, ?)");
            $success = $insert->execute([$_SESSION['user_id'], $data['business_name'], $data['service_radius_km'] ?? 50, $data['base_location']]);
        }

        echo json_encode(['success' => $success, 'message' => $success ? 'Profile saved successfully!' : 'Database error.']);
    }

    // API to Add Land
    public function addLand() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($data['area']) || empty($data['soil_type'])) {
            echo json_encode(['success' => false, 'message' => 'Area and Soil Type are required.']);
            return;
        }

        $landModel = new Land();
        $success = $landModel->addLand($_SESSION['user_id'], $data['area'], $data['soil_type'], $data['irrigation_type'] ?? 'rainfed', $data['location'] ?? '');
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Land added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add land.']);
        }
    }

    // API to Add Crop
    public function addCrop() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($data['land_id']) || empty($data['crop_id']) || empty($data['sowing_date']) || empty($data['area_allocated'])) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            return;
        }

        $cropModel = new Crop();
        $success = $cropModel->addFarmerCrop($_SESSION['user_id'], $data['land_id'], $data['crop_id'], $data['sowing_date'], $data['area_allocated']);
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Crop added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add crop.']);
        }
    }
}
