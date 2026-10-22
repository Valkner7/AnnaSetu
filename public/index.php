<?php
// Session cookie hardening (N6): not readable by JS, not sent cross-site,
// and Secure whenever the request came over HTTPS.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

// Basic security headers (N6).
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
// Browser features: the app uses the location button (mandi/compare) and speech recognition
// (mandi/advisory); everything else is switched off for this site and anything it embeds.
header('Permissions-Policy: geolocation=(self), microphone=(self), camera=(), payment=(), usb=()');
// Tell browsers to stay on HTTPS (180 days). Sent only on HTTPS, where browsers honour it. No
// includeSubDomains or preload, so nothing outside this app is affected.
if ($isHttps) {
    header('Strict-Transport-Security: max-age=15552000');
}

// Content-Security-Policy (O1). The pages use inline scripts and styles, so 'unsafe-inline' stays for
// now; what this adds is a fixed list of allowed hosts (the CDNs the views load), no plugins, no
// foreign <base>, forms only to this site, and framing only by this site. APP_CSP=report-only sends
// it as report-only (nothing is blocked); APP_CSP=off sends no policy.
$cspMode = strtolower((string) (getenv('APP_CSP') ?: 'enforce'));
if ($cspMode !== 'off') {
    $csp = "default-src 'self'; "
        . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
        . "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; "
        . "font-src 'self' data: https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.gstatic.com; "
        . "img-src 'self' data: blob: https://images.unsplash.com; "
        . "media-src 'self' blob: data:; "
        . "connect-src 'self'; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";
    header(($cspMode === 'report-only' ? 'Content-Security-Policy-Report-Only: ' : 'Content-Security-Policy: ') . $csp);
}

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

// HTML-escape helper for views (N2). Use e() for every dynamic value printed
// into HTML text or a quoted attribute. null becomes ''.
if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Check for language switch request
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'hi', 'pa'])) {
    $_SESSION['lang'] = $_GET['lang'];
    // Redirect to the same page without the lang parameter. Only the path and
    // query are reused, and the target is forced to a single leading slash so
    // it can never become a protocol-relative open redirect (N8).
    $parts = parse_url($_SERVER['REQUEST_URI'] ?? '/');
    $path  = '/' . ltrim($parts['path'] ?? '/', '/\\');
    parse_str($parts['query'] ?? '', $query);
    unset($query['lang']);
    $urlNoLang = $path . ($query ? '?' . http_build_query($query) : '');
    header("Location: " . $urlNoLang);
    exit;
}

// Load language (defaults to English until the visitor picks one from the header switcher)
$currentLang = $_SESSION['lang'] ?? 'en';
\App\Helpers\Translator::load($currentLang);

// CSRF protection (L2). Every session gets a random token that the layout
// sends back on each POST as the X-CSRF-Token header (see views/layouts/main.php).
// POSTs without a matching token are rejected before any controller runs.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired. Please reload the page and try again.']);
        exit;
    }
}

// Super simple routing for Phase 1 Demo
$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : 'home';
$urlParts = explode('/', $url);

$controllerName = 'App\\Controllers\\' . ucfirst($urlParts[0]) . 'Controller';
$methodName = isset($urlParts[1]) ? $urlParts[1] : 'index';

// Only letters are valid in a controller name (keeps odd input away from the
// autoloader), and only real public actions may be called (N7). This blocks
// __construct, private/protected methods and anything with odd characters.
$validController = preg_match('/^[A-Za-z]+$/', $urlParts[0]) === 1 && class_exists($controllerName);
$validAction = false;
if ($validController && preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $methodName) && method_exists($controllerName, $methodName)) {
    $ref = new ReflectionMethod($controllerName, $methodName);
    $validAction = $ref->isPublic() && !$ref->isStatic() && !$ref->isConstructor();
}

if ($validController) {
    if ($validAction) {
        $controller = new $controllerName();
        $controller->$methodName();
    } else {
        http_response_code(404);
        echo "404 - Method not found";
    }
} else {
    // Fallback to home
    $controller = new \App\Controllers\HomeController();
    $controller->index();
}
