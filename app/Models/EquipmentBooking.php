<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class EquipmentBooking {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    // For the Owner: see all incoming bookings
    public function getBookingsForOwner($ownerId) {
        $stmt = $this->db->prepare("
            SELECT b.*, e.name as equipment_name, u.phone as farmer_phone, l.location_address, l.area
            FROM equipment_bookings b
            JOIN equipments e ON b.equipment_id = e.id
            JOIN users u ON b.farmer_id = u.id
            JOIN lands l ON b.land_id = l.id
            WHERE e.owner_id = ?
            ORDER BY b.booking_date DESC
        ");
        $stmt->execute([$ownerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // For the Farmer: see all their bookings
    public function getBookingsForFarmer($farmerId) {
        $stmt = $this->db->prepare("
            SELECT b.*, e.name as equipment_name, p.business_name, p.base_location
            FROM equipment_bookings b
            JOIN equipments e ON b.equipment_id = e.id
            JOIN equipment_owner_profiles p ON e.owner_id = p.user_id
            WHERE b.farmer_id = ?
            ORDER BY b.booking_date DESC
        ");
        $stmt->execute([$farmerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
