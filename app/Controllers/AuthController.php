<?php
namespace App\Controllers;

use App\Models\User;
use App\Helpers\LoginThrottle;

class AuthController {

    // Roles a user may choose for themselves at sign-up. Must match the
    // options in views/auth/register.php. 'admin' is deliberately absent:
    // it can only be granted directly in the database.
    private const SELF_REGISTER_ROLES = [
        'farmer',
        'buyer',
        'equipment_owner',
        'storage_provider',
        'transporter',
    ];

    // Web Views
    public function loginView() {
        $title = "Login - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/auth/login.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    public function registerView() {
        $title = "Register - SmartHarvest AI";
        $contentView = __DIR__ . '/../../views/auth/register.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    // API Endpoints for Web (AJAX) and Mobile
    public function register() {
        header('Content-Type: application/json');
        
        // Handle both JSON payload (Mobile App) and Form Data (AJAX)
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($data['first_name']) || empty($data['last_name']) || empty($data['phone']) || empty($data['password']) || empty($data['role'])) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            return;
        }

        // Reject any role the sign-up form does not offer (L1). Checked before
        // the account is created so a bad request leaves no role-less user.
        if (!is_string($data['role']) || !in_array($data['role'], self::SELF_REGISTER_ROLES, true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid role selected.']);
            return;
        }

        // Input checks (N5): phone must be a 10-digit Indian mobile number,
        // password at least 8 characters, names plain strings of sane length.
        foreach (['first_name', 'last_name', 'phone', 'password'] as $field) {
            if (!is_string($data[$field])) {
                echo json_encode(['success' => false, 'message' => 'Invalid input.']);
                return;
            }
        }
        $data['phone'] = trim($data['phone']);
        if (!preg_match('/^[6-9][0-9]{9}$/', $data['phone'])) {
            echo json_encode(['success' => false, 'message' => 'Enter a valid 10-digit mobile number.']);
            return;
        }
        if (strlen($data['password']) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']);
            return;
        }
        if (mb_strlen($data['first_name']) > 50 || mb_strlen($data['last_name']) > 50) {
            echo json_encode(['success' => false, 'message' => 'Name is too long.']);
            return;
        }

        $userModel = new User();
        
        // Check if phone already exists
        if ($userModel->findByPhone($data['phone'])) {
            echo json_encode(['success' => false, 'message' => 'Phone number already registered.']);
            return;
        }

        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $language = $data['language'] ?? 'hi';

        $userId = $userModel->create($data['first_name'], $data['last_name'], $data['phone'], $passwordHash, $language);

        if ($userId) {
            $role = $userModel->getRoleByName($data['role']);
            if ($role) {
                $userModel->assignRole($userId, $role['id']);
            } else {
                // Whitelisted role missing from the roles table: setup problem.
                error_log('Register: role not found in roles table: ' . $data['role']);
            }
            
            // No API token is issued until real token auth exists (L3).
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful.',
                'user' => [
                    'id' => $userId,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => $data['phone'],
                    'role' => $data['role']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed.']);
        }
    }

    public function login() {
        header('Content-Type: application/json');
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($data['phone']) || empty($data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Phone and password are required.']);
            return;
        }

        if (!is_string($data['phone']) || !is_string($data['password'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
            return;
        }

        // Too many recent failures for this phone + IP (N5).
        $wait = LoginThrottle::lockedFor($data['phone']);
        if ($wait > 0) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Too many failed attempts. Try again in ' . ceil($wait / 60) . ' minute(s).']);
            return;
        }

        $userModel = new User();
        $user = $userModel->findByPhone($data['phone']);

        if ($user && password_verify($data['password'], $user['password_hash'])) {
            LoginThrottle::clear($data['phone']);
            
            // If it's a web request (checking a custom header could be better, but session works for web)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            // New session ID on privilege change prevents session fixation (L6).
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_phone'] = $user['phone'];
            $_SESSION['user_role'] = $user['role_name'] ?? 'farmer'; // default fallback
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'phone' => $user['phone']
                ]
            ]);
        } else {
            LoginThrottle::fail($data['phone']);
            echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        }
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        header("Location: " . BASE_URL . "/");
        exit;
    }
}
