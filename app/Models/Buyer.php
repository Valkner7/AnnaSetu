<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class Buyer {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getBuyerProfile($userId) {
        $stmt = $this->db->prepare("SELECT * FROM buyer_profiles WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
