<?php
namespace App\Controllers;

class DashboardController {
    public function index() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Basic Auth Check
        if (!isset($_SESSION['user_id'])) {
            header("Location: /smartharvest/public/index.php?url=auth/loginView");
            exit;
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        $displayName = $_SESSION['user_phone'] ?? 'User'; // Fallback
        
        // Fetch first name as default
        $stmt = $db->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            $displayName = $user['first_name'] . ' ' . $user['last_name'];
        }

        if ($_SESSION['user_role'] === 'buyer') {
            $stmt = $db->prepare("SELECT company_name FROM buyer_profiles WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $bp = $stmt->fetch();
            if ($bp && !empty($bp['company_name'])) {
                $displayName = $bp['company_name'];
            }
        } elseif ($_SESSION['user_role'] === 'equipment_owner') {
            $stmt = $db->prepare("SELECT business_name FROM equipment_owner_profiles WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $ep = $stmt->fetch();
            if ($ep && !empty($ep['business_name'])) {
                $displayName = $ep['business_name'];
            }
        }

        $userRole = $_SESSION['user_role'] ?? 'farmer';
        
        $myInventory = [];
        $myOrders = [];
        
        if ($userRole === 'farmer') {
            $stmt = $db->prepare("
                SELECT cl.*, c.name as crop_name 
                FROM crop_listings cl
                JOIN farmer_crops fc ON cl.farmer_crop_id = fc.id
                JOIN crops c ON fc.crop_id = c.id
                WHERE cl.user_id = ?
                ORDER BY cl.created_at DESC
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $myInventory = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            require_once __DIR__ . '/../Models/ProcurementOrder.php';
            $poModel = new \App\Models\ProcurementOrder();
            $myOrders = $poModel->getOrdersForFarmer($_SESSION['user_id']);
        }

        $title = "Dashboard - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/dashboard/index.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }
}
