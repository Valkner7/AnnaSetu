<?php
/**
 * Static guard for XSS (N2): every <?= ... ?> in views/ must be either
 * wrapped in e(), or be one of the known-safe forms listed below.
 *
 * Run:  php tests/check_output_escaping.php
 * Exit code 0 = clean, 1 = problems found.
 */
$root = dirname(__DIR__) . '/views';

// Expressions that are safe to print without e():
$safePrefixes = [
    'e(',            // already escaped
    'BASE_URL',      // server-derived path (inside JS strings use json_encode, see N13)
    '__(',           // translation strings from lang/*.php
    'number_format(',// numeric output only
    'date(',         // formatted date only
    'count(',        // integer
    'time()',        // integer
    'json_encode(',  // only safe if the caller also escapes it for its context
    '$langSwitchUrl',// built and escaped in layouts/main.php (H2)
];

$problems = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    if ($file->getExtension() !== 'php') continue;
    $src = file_get_contents($file->getPathname());
    $rel = 'views/' . ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');

    // 1) short-echo tags
    preg_match_all('/<\?=\s*(.*?)\s*\?>/s', $src, $m, PREG_OFFSET_CAPTURE);
    foreach ($m[1] as [$expr, $offset]) {
        $ok = false;
        foreach ($safePrefixes as $p) {
            if (strpos($expr, $p) === 0) { $ok = true; break; }
        }
        // constant-only ternaries such as: empty($cart) ? 'disabled' : ''
        if (!$ok && preg_match("/^empty\(.*\)\s*\?\s*'[^'\$]*'\s*:\s*'[^'\$]*'$/s", $expr)) $ok = true;
        if (!$ok) {
            $line = substr_count(substr($src, 0, $offset), "\n") + 1;
            $problems[] = "$rel:$line  <?= $expr ?>";
        }
    }

    // 2) echo / print inside views
    foreach (preg_split('/\R/', $src) as $i => $l) {
        if (preg_match('/<\?php\s+(echo|print)\b/', $l)) {
            $problems[] = "$rel:" . ($i + 1) . "  uses echo/print (use <?= e(...) ?>)";
        }
    }
}

if ($problems) {
    echo "UNESCAPED OUTPUT FOUND:\n  " . implode("\n  ", $problems) . "\n";
    exit(1);
}
echo "OK: all view outputs are escaped or on the safe list.\n";
