<?php
namespace App\Controllers;

use App\Models\Equipment;
use App\Models\EquipmentBooking;
use App\Models\Land;
use App\Core\Database;

class EquipmentController {
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header("Location: /smartharvest/public/index.php?url=auth/loginView");
            exit;
        }
    }

    // --- OWNER SIDE ---

    public function fleet() {
        if ($_SESSION['user_role'] !== 'equipment_owner') {
            header("Location: /smartharvest/public/index.php?url=dashboard");
            exit;
        }
        
        $eqModel = new Equipment();
        $bookingModel = new EquipmentBooking();
        
        $equipments = $eqModel->getEquipmentsByOwner($_SESSION['user_id']);
        $bookings = $bookingModel->getBookingsForOwner($_SESSION['user_id']);
        
        $title = "Manage Fleet - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/equipment/fleet.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    public function addEquipment() {
        header('Content-Type: application/json');
        if ($_SESSION['user_role'] !== 'equipment_owner') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        if (empty($_POST['name']) || empty($_POST['type']) || empty($_POST['rate_per_hour'])) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            return;
        }

        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../public/uploads/equipment/';
            $fileName = time() . '_' . basename($_FILES['image']['name']);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $fileName)) {
                $imagePath = 'uploads/equipment/' . $fileName;
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to upload image.']);
                return;
            }
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO equipments (owner_id, name, type, rate_per_hour, image_path) VALUES (?, ?, ?, ?, ?)");
        $success = $stmt->execute([$_SESSION['user_id'], $_POST['name'], $_POST['type'], $_POST['rate_per_hour'], $imagePath]);

        echo json_encode(['success' => $success, 'message' => $success ? 'Equipment added!' : 'Database error.']);
    }

    public function updateBookingStatus() {
        header('Content-Type: application/json');
        if ($_SESSION['user_role'] !== 'equipment_owner') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['booking_id']) || empty($data['status'])) {
            echo json_encode(['success' => false, 'message' => 'Missing data.']);
            return;
        }

        $db = Database::getInstance()->getConnection();
        // Verify owner owns this equipment booking
        $stmt = $db->prepare("
            UPDATE equipment_bookings b 
            JOIN equipments e ON b.equipment_id = e.id 
            SET b.status = ? 
            WHERE b.id = ? AND e.owner_id = ?
        ");
        $success = $stmt->execute([$data['status'], $data['booking_id'], $_SESSION['user_id']]);

        echo json_encode(['success' => $success, 'message' => $success ? 'Status updated!' : 'Failed.']);
    }


    // --- FARMER SIDE ---

    public function marketplace() {
        if ($_SESSION['user_role'] !== 'farmer') {
            header("Location: /smartharvest/public/index.php?url=dashboard");
            exit;
        }
        
        $eqModel = new Equipment();
        $landModel = new Land();
        $bookingModel = new EquipmentBooking();
        
        $equipments = $eqModel->getAllActiveEquipments();
        $lands = $landModel->getLandsByUser($_SESSION['user_id']);
        $myBookings = $bookingModel->getBookingsForFarmer($_SESSION['user_id']);
        
        $title = "Equipment Marketplace - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/equipment/marketplace.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    public function bookEquipment() {
        header('Content-Type: application/json');
        if ($_SESSION['user_role'] !== 'farmer') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['equipment_id']) || empty($data['land_id']) || empty($data['booking_date']) || empty($data['duration_hours'])) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            return;
        }

        $db = Database::getInstance()->getConnection();
        
        // Calculate cost
        $stmt = $db->prepare("SELECT rate_per_hour FROM equipments WHERE id = ?");
        $stmt->execute([$data['equipment_id']]);
        $eq = $stmt->fetch();
        if(!$eq) {
            echo json_encode(['success' => false, 'message' => 'Invalid equipment.']);
            return;
        }
        
        $totalCost = $eq['rate_per_hour'] * $data['duration_hours'];

        $insert = $db->prepare("INSERT INTO equipment_bookings (equipment_id, farmer_id, land_id, booking_date, duration_hours, total_cost) VALUES (?, ?, ?, ?, ?, ?)");
        $success = $insert->execute([
            $data['equipment_id'], 
            $_SESSION['user_id'], 
            $data['land_id'], 
            $data['booking_date'], 
            $data['duration_hours'],
            $totalCost
        ]);

        echo json_encode(['success' => $success, 'message' => $success ? 'Booking request sent!' : 'Database error.']);
    }

    public function cancelBooking() {
        header('Content-Type: application/json');
        if ($_SESSION['user_role'] !== 'farmer') {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['booking_id'])) {
            echo json_encode(['success' => false, 'message' => 'Missing booking ID.']);
            return;
        }

        $db = \App\Core\Database::getInstance()->getConnection();
        // Only allow cancelling if the status is still 'pending'
        $stmt = $db->prepare("UPDATE equipment_bookings SET status = 'cancelled' WHERE id = ? AND farmer_id = ? AND status = 'pending'");
        $stmt->execute([$data['booking_id'], $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Cannot cancel this booking.']);
        }
    }
}
