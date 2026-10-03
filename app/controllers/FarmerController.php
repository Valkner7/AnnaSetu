<?php
namespace App\Controllers;

use App\Models\Crop;
use App\Models\Land;
use App\Core\Database;

class FarmerController {
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
            header("Location: /smartharvest/public/index.php?url=dashboard");
            exit;
        }
    }

    // --- CROP PLANNING (AI SIMULATION) ---
    public function cropPlanning() {
        $landModel = new Land();
        $lands = $landModel->getLandsByUser($_SESSION['user_id']);
        
        $title = "AI Crop Planning - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/farmer/crop_planning.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    public function getAiRecommendation() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        // In a real scenario, this makes a cURL request to the Python FastAPI microservice.
        // For Phase 3, we simulate the AI response based on the prompt's requirements.
        
        // Simulated Python AI Response
        $aiResponse = [
            'success' => true,
            'recommendations' => [
                [
                    'crop' => 'Tomato',
                    'crop_hi' => 'टमाटर',
                    'suitability_score' => 92,
                    'expected_duration' => '90-100 Days',
                    'water_requirement' => 'Medium',
                    'estimated_yield' => '10 - 12 Tons/Acre',
                    'estimated_revenue' => '₹1,50,000 - ₹2,00,000',
                    'risk_level' => 'Low',
                    'reason' => 'Perfect for current soil type and upcoming weather patterns.'
                ],
                [
                    'crop' => 'Onion',
                    'crop_hi' => 'प्याज',
                    'suitability_score' => 85,
                    'expected_duration' => '110-120 Days',
                    'water_requirement' => 'Low',
                    'estimated_yield' => '8 - 10 Tons/Acre',
                    'estimated_revenue' => '₹1,20,000 - ₹1,80,000',
                    'risk_level' => 'Medium',
                    'reason' => 'Good market demand, but requires proper storage.'
                ]
            ],
            'disclaimer' => '* AI predictions are estimates based on historical data and do not guarantee outcomes.'
        ];

        echo json_encode($aiResponse);
    }

    // --- MARKETPLACE LISTING ---
    public function listProduce() {
        $cropModel = new Crop();
        $myCrops = $cropModel->getFarmerCrops($_SESSION['user_id']);
        
        $title = "Sell Produce - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/farmer/list_produce.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    public function saveListing() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($data['farmer_crop_id']) || empty($data['quantity_kg']) || empty($data['expected_price'])) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            return;
        }

        $db = Database::getInstance()->getConnection();
        
        // Fetch the crop details to set the harvest date
        $stmtCrop = $db->prepare("SELECT expected_harvest_date FROM farmer_crops WHERE id = ? AND user_id = ?");
        $stmtCrop->execute([$data['farmer_crop_id'], $_SESSION['user_id']]);
        $crop = $stmtCrop->fetch();

        if (!$crop) {
            echo json_encode(['success' => false, 'message' => 'Invalid crop selection.']);
            return;
        }

        $stmt = $db->prepare("INSERT INTO crop_listings (user_id, farmer_crop_id, quantity_kg, expected_price_per_kg, harvest_date) VALUES (?, ?, ?, ?, ?)");
        $success = $stmt->execute([
            $_SESSION['user_id'], 
            $data['farmer_crop_id'], 
            $data['quantity_kg'], 
            $data['expected_price'], 
            $data['harvest_date'] ?? $crop['expected_harvest_date']
        ]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Produce successfully listed on the marketplace!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to list produce.']);
        }
    }

    public function updateOrderStatus() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['order_id']) || empty($data['status'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
            return;
        }

        $db = Database::getInstance()->getConnection();
        
        // Verify this order belongs to a listing owned by this farmer
        $stmt = $db->prepare("
            SELECT po.id 
            FROM procurement_orders po
            JOIN crop_listings cl ON po.crop_listing_id = cl.id
            WHERE po.id = ? AND cl.user_id = ?
        ");
        $stmt->execute([$data['order_id'], $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized or order not found.']);
            return;
        }

        // Update the status
        $updateStmt = $db->prepare("UPDATE procurement_orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $success = $updateStmt->execute([$data['status'], $data['order_id']]);

        echo json_encode(['success' => $success, 'message' => $success ? 'Order updated.' : 'Database error.']);
    }
}
