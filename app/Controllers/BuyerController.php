<?php
namespace App\Controllers;

use App\Core\Database;
use App\Models\ProcurementOrder;
use PDO;

class BuyerController {
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . BASE_URL . "/index.php?url=auth/loginView");
            exit;
        }
    }

    public function marketplace() {
        if ($_SESSION['user_role'] !== 'buyer') {
            header("Location: " . BASE_URL . "/index.php?url=dashboard");
            exit;
        }

        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT cl.id, cl.quantity_kg, cl.expected_price_per_kg, cl.harvest_date, cl.grade,
                   c.name as crop_name, c.name_hi, c.name_pa, 
                   u.first_name, l.location_address
            FROM crop_listings cl
            JOIN farmer_crops fc ON cl.farmer_crop_id = fc.id
            JOIN crops c ON fc.crop_id = c.id
            JOIN lands l ON fc.land_id = l.id
            JOIN users u ON cl.user_id = u.id
            WHERE cl.status = 'active'
            ORDER BY cl.created_at DESC
        ");
        $stmt->execute();
        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $title = "Procurement Marketplace - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/buyer/marketplace.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    public function addToCart() {
        header('Content-Type: application/json');
        if ($_SESSION['user_role'] !== 'buyer') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['listing_id']) || empty($data['quantity']) || empty($data['price'])) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            return;
        }

        if (!isset($_SESSION['buyer_cart'])) {
            $_SESSION['buyer_cart'] = [];
        }

        // Add to cart session
        $_SESSION['buyer_cart'][$data['listing_id']] = [
            'listing_id' => $data['listing_id'],
            'quantity' => $data['quantity'],
            'price' => $data['price'],
            'name' => $data['name'] ?? 'Crop'
        ];

        echo json_encode(['success' => true, 'message' => 'Added to Procurement Cart!']);
    }

    public function viewCart() {
        if ($_SESSION['user_role'] !== 'buyer') {
            header("Location: " . BASE_URL . "/index.php?url=dashboard");
            exit;
        }

        $cart = $_SESSION['buyer_cart'] ?? [];
        $title = "Procurement Cart - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/buyer/cart.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    public function removeFromCart() {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['listing_id']) && isset($_SESSION['buyer_cart'][$data['listing_id']])) {
            unset($_SESSION['buyer_cart'][$data['listing_id']]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    public function checkout() {
        header('Content-Type: application/json');
        if ($_SESSION['user_role'] !== 'buyer' || empty($_SESSION['buyer_cart'])) {
            echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
            return;
        }

        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare("INSERT INTO procurement_orders (buyer_id, crop_listing_id, quantity_tons, offered_price_per_quintal) VALUES (?, ?, ?, ?)");
            
            foreach ($_SESSION['buyer_cart'] as $item) {
                $stmt->execute([
                    $_SESSION['user_id'], 
                    $item['listing_id'], 
                    $item['quantity'], 
                    $item['price']
                ]);
            }
            
            $db->commit();
            unset($_SESSION['buyer_cart']); // Clear cart
            
            echo json_encode(['success' => true, 'message' => 'Procurement Orders placed successfully!']);
        } catch (\Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'Checkout failed.']);
        }
    }

    public function myOrders() {
        if ($_SESSION['user_role'] !== 'buyer') {
            header("Location: " . BASE_URL . "/index.php?url=dashboard");
            exit;
        }

        $orderModel = new ProcurementOrder();
        $orders = $orderModel->getOrdersByBuyer($_SESSION['user_id']);

        $title = "My Procurement Orders - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/buyer/orders.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }
}
