<?php
// Every Market Prices sub-menu link and every endpoint the pages call must reach a real,
// public controller method, resolved exactly as public/index.php does it (url=controller/method).
// Run: php tests/check_routes.php
define('BASE_URL', '/app');
spl_autoload_register(function ($class) {
    if (strncmp('App\\', $class, 4) !== 0) { return; }
    $f = __DIR__ . '/../app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($f)) { require $f; }
});
$fail = 0;
function check($name, $ok) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; if (!$ok) { $fail++; } }

function resolves(string $url): bool {
    $parts = explode('/', rtrim($url, '/'));
    $class = 'App\\Controllers\\' . ucfirst($parts[0]) . 'Controller';
    $method = $parts[1] ?? 'index';
    if (!preg_match('/^[A-Za-z]+$/', $parts[0]) || !class_exists($class) || !method_exists($class, $method)) { return false; }
    $ref = new ReflectionMethod($class, $method);
    return $ref->isPublic() && !$ref->isStatic() && !$ref->isConstructor();
}

// links in the sub-menu partial
preg_match_all("/=> \['([a-z\/]+)',\s*'fa-/", file_get_contents(__DIR__ . '/../views/mandi/_nav.php'), $m);
check('sub-menu has its 4 tabs', count($m[1]) === 4);
foreach ($m[1] as $u) { check("menu link '$u' resolves", resolves($u)); }

// endpoints called by the page scripts (grep every 'url=...' style target in views/mandi)
$targets = ['mandi/mandis', 'mandi/forecast', 'mandi/history', 'mandi/comparison', 'mandi/nearby', 'mandi/health', 'mandi/compare',
            'alerts/create', 'alerts/delete', 'alerts/rearm', 'alerts/check', 'advisory/ask', 'advisory/speak'];
foreach ($targets as $u) { check("endpoint '$u' resolves", resolves($u)); }
// and every one the views mention must be in the list above
$seen = [];
foreach (glob(__DIR__ . '/../views/mandi/*.php') as $f) {
    preg_match_all("/url=((?:mandi|alerts|advisory)(?:\/[A-Za-z]+)?)/", file_get_contents($f), $mm);
    foreach ($mm[1] as $u) { $seen[$u] = true; }
}
foreach (array_keys($seen) as $u) { check("view reference '$u' resolves", resolves($u)); }
check('private methods are not reachable', !resolves('alerts/postOnly') && !resolves('alerts/__construct'));
echo $fail === 0 ? "\nOK: all route checks passed.\n" : "\n$fail check(s) FAILED.\n";
exit($fail === 0 ? 0 : 1);
