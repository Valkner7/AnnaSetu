<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByPhone($phone) {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u 
            LEFT JOIN user_roles ur ON u.id = ur.user_id 
            LEFT JOIN roles r ON ur.role_id = r.id 
            WHERE u.phone = ? LIMIT 1
        ");
        $stmt->execute([$phone]);
        return $stmt->fetch();
    }

    public function create($firstName, $lastName, $phone, $passwordHash, $language = 'hi') {
        $stmt = $this->db->prepare("INSERT INTO users (first_name, last_name, phone, password_hash, preferred_language) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$firstName, $lastName, $phone, $passwordHash, $language])) {
            return $this->db->lastInsertId();
        }
        return false;
    }

    public function assignRole($userId, $roleId) {
        $stmt = $this->db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
        return $stmt->execute([$userId, $roleId]);
    }

    public function getRoleByName($roleName) {
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = ? LIMIT 1");
        $stmt->execute([$roleName]);
        return $stmt->fetch();
    }
}
