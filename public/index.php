<?php
session_start();

// Base URL path of this app (no trailing slash). Derived from where index.php is served,
// e.g. /smartharvest/public on XAMPP, or empty when public/ is the web root.
// Override with the APP_BASE_URL environment variable if needed.
if (!defined('BASE_URL')) {
    $envBase = getenv('APP_BASE_URL');
    if ($envBase !== false && $envBase !== '') {
        define('BASE_URL', rtrim($envBase, '/'));
    } else {
        define('BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/'));
    }
}
/**
 * SmartHarvest AI - From Seed to Sale
 * Entry point for the application.
 */

// Basic Class Autoloader for the App namespace
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Setup Multilingual Framework
require_once __DIR__ . '/../app/Helpers/Translator.php';

// Global helper function for easier use in views
if (!function_exists('__')) {
    function __($key) {
        return \App\Helpers\Translator::get($key);
    }
}

// Check for language switch request
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'hi', 'pa'])) {
    $_SESSION['lang'] = $_GET['lang'];
    // Redirect to same URL without the lang parameter to clean URL
    $urlNoLang = preg_replace('/([&?])lang=[^&]+(&|$)/', '$1', $_SERVER['REQUEST_URI']);
    $urlNoLang = rtrim($urlNoLang, '?&');
    header("Location: " . $urlNoLang);
    exit;
}

// Load language (default to Hindi if not set, as it's an Indian agri platform)
$currentLang = $_SESSION['lang'] ?? 'en';
\App\Helpers\Translator::load($currentLang);

// Super simple routing for Phase 1 Demo
$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : 'home';
$urlParts = explode('/', $url);

$controllerName = 'App\\Controllers\\' . ucfirst($urlParts[0]) . 'Controller';
$methodName = isset($urlParts[1]) ? $urlParts[1] : 'index';

if (class_exists($controllerName)) {
    $controller = new $controllerName();
    if (method_exists($controller, $methodName)) {
        $controller->$methodName();
    } else {
        echo "404 - Method not found";
    }
} else {
    // Fallback to home
    $controller = new \App\Controllers\HomeController();
    $controller->index();
}
