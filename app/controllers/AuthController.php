<?php
namespace App\Controllers;

use App\Models\User;

class AuthController {
    
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
            }
            
            // In a real mobile app API, you would generate a JWT token here.
            // For now, we return a success response with a simulated token.
            echo json_encode([
                'success' => true, 
                'message' => 'Registration successful.',
                'token' => 'simulated_jwt_token_for_mobile_' . $userId,
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

        $userModel = new User();
        $user = $userModel->findByPhone($data['phone']);

        if ($user && password_verify($data['password'], $user['password_hash'])) {
            
            // Generate simulated JWT Token for mobile/web session
            $token = 'simulated_jwt_token_for_mobile_' . $user['id'];
            
            // If it's a web request (checking a custom header could be better, but session works for web)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_phone'] = $user['phone'];
            $_SESSION['user_role'] = $user['role_name'] ?? 'farmer'; // default fallback
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'user' => [
                    'id' => $user['id'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'phone' => $user['phone']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
        }
    }

    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
        header("Location: /smartharvest/public/");
        exit;
    }
}
