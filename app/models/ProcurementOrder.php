<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class ProcurementOrder {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getOrdersByBuyer($buyerId) {
        $stmt = $this->db->prepare("
            SELECT po.*, cl.quantity_kg, cl.expected_price_per_kg, c.name as crop_name, c.name_hi as crop_name_hi, u.first_name, u.phone as farmer_phone
            FROM procurement_orders po
            JOIN crop_listings cl ON po.crop_listing_id = cl.id
            JOIN farmer_crops fc ON cl.farmer_crop_id = fc.id
            JOIN crops c ON fc.crop_id = c.id
            JOIN users u ON cl.user_id = u.id
            WHERE po.buyer_id = ?
            ORDER BY po.created_at DESC
        ");
        $stmt->execute([$buyerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrdersForFarmer($farmerId) {
        $stmt = $this->db->prepare("
            SELECT po.*, bp.company_name, bp.delivery_address, u.phone as buyer_phone, c.name as crop_name, cl.quantity_kg as listed_qty
            FROM procurement_orders po
            JOIN crop_listings cl ON po.crop_listing_id = cl.id
            JOIN buyer_profiles bp ON po.buyer_id = bp.user_id
            JOIN users u ON po.buyer_id = u.id
            JOIN farmer_crops fc ON cl.farmer_crop_id = fc.id
            JOIN crops c ON fc.crop_id = c.id
            WHERE cl.user_id = ?
            ORDER BY po.created_at DESC
        ");
        $stmt->execute([$farmerId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
