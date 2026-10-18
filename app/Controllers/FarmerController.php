<?php
namespace App\Controllers;

use App\Helpers\Validator;
use App\Models\Crop;
use App\Models\Land;
use App\Core\Database;
use App\Helpers\CropOutlook;
use App\Helpers\MandiText;
use App\Helpers\Translator;
use App\Services\MandiService;

class FarmerController {
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'farmer') {
            header("Location: " . BASE_URL . "/index.php?url=dashboard");
            exit;
        }
    }

    // --- CROP PLANNING: PRICE COMPARISON (F2) ---
    public function cropPlanning() {
        $crops = $this->mandiService()->getReliableCrops(false);   // cache only: never wait on a cold start
        $title = "Crop Planning - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/farmer/crop_planning.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    /** Overridable so tests can inject a fake price service. */
    protected function mandiService() {
        return new MandiService();
    }

    /**
     * Real past prices for every forecastable crop in one mandi. Replaces the
     * old simulated Tomato/Onion answer. Returns figures about what already
     * happened (see CropOutlook); it never invents yield or revenue.
     */
    public function getAiRecommendation() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data)) {
            $data = $_POST;
        }
        $mandi = is_string($data['mandi'] ?? null) ? trim($data['mandi']) : '';
        if ($mandi === '' || strlen($mandi) > 80 || preg_match('/[\x00-\x1F\x7F]/', $mandi)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'choose_mandi']);
            return;
        }
        session_write_close();      // the price service can be slow; do not hold the session lock

        $svc = $this->mandiService();
        $today = date('Y-m-d');
        $t = function ($k) { return Translator::get($k); };
        $rows = [];
        $stale = false;
        $anyReached = false;
        foreach ($svc->getReliableCrops() as $crop) {
            $res = $svc->getHistory($crop, $mandi, 365);
            $row = ['crop' => $crop, 'label' => MandiText::cropLabel($crop, $t), 'status' => 'no_data'];
            if ($res['ok']) {
                $anyReached = true;
                $stale = $stale || !empty($res['stale']);
                $row = array_merge($row, CropOutlook::summarize($res['data']['points'] ?? [], $today));
            } elseif ($res['status'] === 0 || $res['status'] >= 500) {
                $row['status'] = 'unavailable';
            } else {
                $anyReached = true;     // 404/422: the service answered, this crop is not sold here
            }
            $rows[] = $row;
        }
        if (!$anyReached) {
            http_response_code(502);
            echo json_encode(['success' => false, 'error' => 'service_down']);
            return;
        }
        echo json_encode(['success' => true, 'mandi' => $mandi, 'unit' => 'INR per quintal',
                          'crops' => $rows, 'served_stale' => $stale], JSON_UNESCAPED_UNICODE);
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

        // Server-side validation (N4).
        $cropId = Validator::id($data['farmer_crop_id']);
        $qtyKg  = Validator::number($data['quantity_kg'], 0.01, 10000000);
        $price  = Validator::number($data['expected_price'], 0.01, 100000);   // Rs per kg
        $harvest = isset($data['harvest_date']) && $data['harvest_date'] !== '' ? Validator::date($data['harvest_date']) : null;
        if ($cropId === null || $qtyKg === null || $price === null
            || (isset($data['harvest_date']) && $data['harvest_date'] !== '' && $harvest === null)) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid quantity, price and harvest date.']);
            return;
        }

        $db = Database::getInstance()->getConnection();
        
        // Fetch the crop details to set the harvest date
        $stmtCrop = $db->prepare("SELECT expected_harvest_date FROM farmer_crops WHERE id = ? AND user_id = ?");
        $stmtCrop->execute([$cropId, $_SESSION['user_id']]);
        $crop = $stmtCrop->fetch();

        if (!$crop) {
            echo json_encode(['success' => false, 'message' => 'Invalid crop selection.']);
            return;
        }

        $stmt = $db->prepare("INSERT INTO crop_listings (user_id, farmer_crop_id, quantity_kg, expected_price_per_kg, harvest_date) VALUES (?, ?, ?, ?, ?)");
        $success = $stmt->execute([
            $_SESSION['user_id'], 
            $cropId, 
            $qtyKg, 
            $price, 
            $harvest ?? $crop['expected_harvest_date']
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
        // Only real order statuses, and a real id (N4).
        $orderId = Validator::id($data['order_id']);
        $status  = Validator::oneOf($data['status'], ['accepted', 'rejected', 'completed']);
        if ($orderId === null || $status === null) {
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
        $stmt->execute([$orderId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized or order not found.']);
            return;
        }

        // Update the status
        $updateStmt = $db->prepare("UPDATE procurement_orders SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $success = $updateStmt->execute([$status, $orderId]);

        echo json_encode(['success' => $success, 'message' => $success ? 'Order updated.' : 'Database error.']);
    }
}
