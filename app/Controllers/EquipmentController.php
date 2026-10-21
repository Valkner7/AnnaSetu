<?php
namespace App\Controllers;

use App\Helpers\Validator;
use App\Models\Equipment;
use App\Models\EquipmentBooking;
use App\Models\Land;
use App\Core\Database;
use App\Helpers\ImageUpload;

class EquipmentController {
    
    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . BASE_URL . "/index.php?url=auth/loginView");
            exit;
        }
    }

    // --- OWNER SIDE ---

    public function fleet() {
        if ($_SESSION['user_role'] !== 'equipment_owner') {
            header("Location: " . BASE_URL . "/index.php?url=dashboard");
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
        // Server-side validation (N4).
        $eqName = Validator::text($_POST['name'], 100);
        $eqType = Validator::text($_POST['type'], 50);
        $eqRate = Validator::number($_POST['rate_per_hour'], 1, 100000);   // Rs per hour
        if ($eqName === null || $eqType === null || $eqRate === null) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid name, type and hourly rate.']);
            return;
        }

        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
            $upload = ImageUpload::save($_FILES['image'], 'equipment');
            if (!$upload['ok']) {
                echo json_encode(['success' => false, 'message' => $upload['error']]);
                return;
            }
            $imagePath = $upload['path'];
        }

        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("INSERT INTO equipments (owner_id, name, type, rate_per_hour, image_path) VALUES (?, ?, ?, ?, ?)");
        $success = $stmt->execute([$_SESSION['user_id'], $eqName, $eqType, $eqRate, $imagePath]);

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
        // Only real booking statuses, and a real id (N4).
        $bookingId = Validator::id($data['booking_id']);
        $status    = Validator::oneOf($data['status'], ['confirmed', 'completed', 'cancelled']);
        if ($bookingId === null || $status === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
            return;
        }

        $db = Database::getInstance()->getConnection();

        // Read the booking and lock it together with its equipment row, in one transaction.
        // Locking the equipment row means two simultaneous confirms for the same machine
        // take turns, so the second one sees the first and cannot also win (double booking).
        // The join on owner_id also checks that this owner owns the equipment.
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                SELECT b.equipment_id, b.booking_date, b.status
                FROM equipment_bookings b
                JOIN equipments e ON b.equipment_id = e.id
                WHERE b.id = ? AND e.owner_id = ?
                FOR UPDATE
            ");
            $stmt->execute([$bookingId, $_SESSION['user_id']]);
            $booking = $stmt->fetch();

            if (!$booking) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'Booking not found.']);
                return;
            }

            // A booking only moves forward: pending -> confirmed or cancelled,
            // confirmed -> completed or cancelled. Completed and cancelled are final.
            $allowed = [
                'pending'   => ['confirmed', 'cancelled'],
                'confirmed' => ['completed', 'cancelled'],
            ];
            if (!in_array($status, $allowed[$booking['status']] ?? [], true)) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => $booking['status'] === $status
                    ? 'This booking is already ' . $status . '.'
                    : 'A ' . $booking['status'] . ' booking cannot be changed to ' . $status . '.']);
                return;
            }

            // One confirmed booking per machine per day (the table stores only a date).
            if ($status === 'confirmed' && $this->hasConfirmedBooking((int)$booking['equipment_id'], $booking['booking_date'], $bookingId)) {
                $db->rollBack();
                echo json_encode(['success' => false, 'message' => 'This equipment is already confirmed for another farmer on that date.']);
                return;
            }

            $update = $db->prepare("UPDATE equipment_bookings SET status = ? WHERE id = ?");
            $update->execute([$status, $bookingId]);
            $changed = $update->rowCount() > 0;
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('updateBookingStatus failed: ' . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Could not update the booking. Please try again.']);
            return;
        }

        echo json_encode(['success' => $changed, 'message' => $changed ? 'Status updated!' : 'The booking was not changed.']);
    }

    /** True when another booking for this machine on this date is already confirmed. */
    private function hasConfirmedBooking(int $equipmentId, string $date, int $exceptBookingId = 0): bool {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT 1 FROM equipment_bookings
                              WHERE equipment_id = ? AND booking_date = ? AND status = 'confirmed' AND id <> ? LIMIT 1");
        $stmt->execute([$equipmentId, $date, $exceptBookingId]);
        return (bool)$stmt->fetchColumn();
    }


    // --- FARMER SIDE ---

    public function marketplace() {
        if ($_SESSION['user_role'] !== 'farmer') {
            header("Location: " . BASE_URL . "/index.php?url=dashboard");
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

        // Server-side validation (N4): real ids, a future date, 1 to 720 hours.
        $equipmentId = Validator::id($data['equipment_id']);
        $landId      = Validator::id($data['land_id']);
        $bookingDate = Validator::date($data['booking_date'], true);
        $hours       = Validator::number($data['duration_hours'], 0.5, 720);
        if ($equipmentId === null || $landId === null || $bookingDate === null || $hours === null) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid date (not in the past) and duration.']);
            return;
        }
        $data['equipment_id'] = $equipmentId;
        $data['land_id'] = $landId;

        // The land must belong to the farmer who is booking (N3). Without this a
        // farmer could name someone else's land and the owner would see its address.
        if (!(new Land())->belongsToUser($data['land_id'], $_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid land selection.']);
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
        
        $totalCost = $eq['rate_per_hour'] * $hours;

        // Refuse a day that is already confirmed for this machine, and a repeat of this
        // farmer's own open request. Several farmers may still have pending requests for the
        // same day; the owner picks one, and confirming is checked again when they do.
        if ($this->hasConfirmedBooking($data['equipment_id'], $bookingDate)) {
            echo json_encode(['success' => false, 'message' => 'This equipment is already booked on that date. Please choose another date.']);
            return;
        }
        $dup = $db->prepare("SELECT 1 FROM equipment_bookings
                             WHERE equipment_id = ? AND farmer_id = ? AND booking_date = ? AND status IN ('pending', 'confirmed') LIMIT 1");
        $dup->execute([$data['equipment_id'], $_SESSION['user_id'], $bookingDate]);
        if ($dup->fetchColumn()) {
            echo json_encode(['success' => false, 'message' => 'You already have a booking request for this equipment on that date.']);
            return;
        }

        $insert = $db->prepare("INSERT INTO equipment_bookings (equipment_id, farmer_id, land_id, booking_date, duration_hours, total_cost) VALUES (?, ?, ?, ?, ?, ?)");
        $success = $insert->execute([
            $data['equipment_id'], 
            $_SESSION['user_id'], 
            $data['land_id'], 
            $bookingDate, 
            $hours,
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
