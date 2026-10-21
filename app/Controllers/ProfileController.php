<?php
namespace App\Controllers;

use App\Helpers\Validator;
use App\Models\Land;
use App\Models\Crop;

class ProfileController {
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . BASE_URL . "/index.php?url=auth/loginView");
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
        if (!is_array($data)) {
            $data = [];
        }
        if (empty($data['company_name'])) {
            echo json_encode(['success' => false, 'message' => 'Company Name is required.']);
            return;
        }
        // Limits follow the buyer_profiles columns: company_name 255, gstin 50, capacity INT.
        $company  = Validator::text($data['company_name'], 255);
        $gstin    = (isset($data['gstin']) && $data['gstin'] !== '') ? Validator::text($data['gstin'], 50) : null;
        $capacity = (isset($data['procurement_capacity']) && $data['procurement_capacity'] !== '')
            ? Validator::wholeNumber($data['procurement_capacity'], 0, 100000000) : 0;
        $address  = (isset($data['delivery_address']) && $data['delivery_address'] !== '')
            ? Validator::text($data['delivery_address'], 1000) : null;
        if ($company === null
            || (isset($data['gstin']) && $data['gstin'] !== '' && $gstin === null)
            || $capacity === null
            || (isset($data['delivery_address']) && $data['delivery_address'] !== '' && $address === null)) {
            echo json_encode(['success' => false, 'message' => 'Check the details: company name up to 255 characters, GSTIN up to 50, capacity a whole number, address up to 1000.']);
            return;
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT user_id FROM buyer_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $update = $db->prepare("UPDATE buyer_profiles SET company_name=?, gstin=?, procurement_capacity=?, delivery_address=? WHERE user_id=?");
            $success = $update->execute([$company, $gstin, $capacity, $address, $_SESSION['user_id']]);
        } else {
            $insert = $db->prepare("INSERT INTO buyer_profiles (user_id, company_name, gstin, procurement_capacity, delivery_address) VALUES (?, ?, ?, ?, ?)");
            $success = $insert->execute([$_SESSION['user_id'], $company, $gstin, $capacity, $address]);
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
        if (!is_array($data)) {
            $data = [];
        }
        if (empty($data['business_name']) || empty($data['base_location'])) {
            echo json_encode(['success' => false, 'message' => 'Business Name and Base Location are required.']);
            return;
        }
        // Limits follow the equipment_owner_profiles columns: both names 255, radius an INT.
        $business = Validator::text($data['business_name'], 255);
        $location = Validator::text($data['base_location'], 255);
        $radius   = (isset($data['service_radius_km']) && $data['service_radius_km'] !== '')
            ? Validator::wholeNumber($data['service_radius_km'], 1, 2000) : 50;
        if ($business === null || $location === null || $radius === null) {
            echo json_encode(['success' => false, 'message' => 'Check the details: names up to 255 characters, service radius a whole number of km from 1 to 2000.']);
            return;
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("SELECT user_id FROM equipment_owner_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $update = $db->prepare("UPDATE equipment_owner_profiles SET business_name=?, service_radius_km=?, base_location=? WHERE user_id=?");
            $success = $update->execute([$business, $radius, $location, $_SESSION['user_id']]);
        } else {
            $insert = $db->prepare("INSERT INTO equipment_owner_profiles (user_id, business_name, service_radius_km, base_location) VALUES (?, ?, ?, ?)");
            $success = $insert->execute([$_SESSION['user_id'], $business, $radius, $location]);
        }

        echo json_encode(['success' => $success, 'message' => $success ? 'Profile saved successfully!' : 'Database error.']);
    }

    // Only farmers own land and crops (N3). Sends the JSON error itself.
    private function requireFarmer(): bool {
        if (($_SESSION['user_role'] ?? '') !== 'farmer') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return false;
        }
        return true;
    }

    // API to Add Land
    public function addLand() {
        header('Content-Type: application/json');
        if (!$this->requireFarmer()) {
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($data['area']) || empty($data['soil_type'])) {
            echo json_encode(['success' => false, 'message' => 'Area and Soil Type are required.']);
            return;
        }

        // Server-side validation (N4).
        $area = Validator::number($data['area'], 0.01, 100000);
        $soil = Validator::text($data['soil_type'], 50);
        $irrigation = Validator::oneOf($data['irrigation_type'] ?? 'rainfed', ['rainfed', 'tubewell', 'canal']);
        $location = isset($data['location']) && $data['location'] !== '' ? Validator::text($data['location'], 255) : '';
        if ($area === null || $soil === null || $irrigation === null || $location === null) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid area, soil type and location.']);
            return;
        }

        $landModel = new Land();
        $success = $landModel->addLand($_SESSION['user_id'], $area, $soil, $irrigation, $location);
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Land added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add land.']);
        }
    }

    // API to Add Crop
    public function addCrop() {
        header('Content-Type: application/json');
        if (!$this->requireFarmer()) {
            return;
        }
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($data['land_id']) || empty($data['crop_id']) || empty($data['sowing_date']) || empty($data['area_allocated'])) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            return;
        }

        // Server-side validation (N4).
        $sowing = Validator::date($data['sowing_date']);
        $areaAlloc = Validator::number($data['area_allocated'], 0.01, 100000);
        $data['land_id'] = Validator::id($data['land_id']);
        $data['crop_id'] = Validator::id($data['crop_id']);
        if ($sowing === null || $areaAlloc === null || $data['land_id'] === null || $data['crop_id'] === null) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid sowing date and area.']);
            return;
        }

        // The land must be the farmer's own, and the crop must exist (N3).
        if (!(new Land())->belongsToUser($data['land_id'], $_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid land selection.']);
            return;
        }
        $cropModel = new Crop();
        if (!$cropModel->masterCropExists($data['crop_id'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid crop selection.']);
            return;
        }
        $success = $cropModel->addFarmerCrop($_SESSION['user_id'], $data['land_id'], $data['crop_id'], $sowing, $areaAlloc);
        
        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Crop added successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add crop.']);
        }
    }
}
