<?php
// Run: php tests/check_helper_scripts_cli.php   (exit 0 = pass)
// The one-off scripts in the project root change the database. They must refuse to run over HTTP,
// and fix_crops.php (which empties the crops table) must also refuse without an explicit flag.
// Source checks plus a real run of the refusal paths; nothing here needs a database.
$root = dirname(__DIR__);
$scripts = ['fix_crops.php', 'fix_crops_pa.php', 'run_schema4.php', 'run_schema5.php', 'run_schema7.php'];
$fail = 0;
function check($name, $ok) { global $fail; echo ($ok ? 'PASS ' : 'FAIL ') . $name . "\n"; $fail += $ok ? 0 : 1; }

foreach ($scripts as $f) {
    $src = (string) @file_get_contents("$root/$f");
    $guard = strpos($src, "PHP_SAPI !== 'cli'");
    $use = strpos($src, 'Database.php');
    check("$f has a CLI-only guard", $guard !== false);
    check("$f checks the guard before it loads the database", $guard !== false && $use !== false && $guard < $use);
}

// fix_crops.php without the flag: must refuse, exit 1, and say how to proceed (no database touched).
$php = escapeshellarg(PHP_BINARY);
$out = [];
exec("$php " . escapeshellarg("$root/fix_crops.php") . ' 2>&1', $out, $code);
$text = implode("\n", $out);
check('fix_crops.php without the flag exits 1', $code === 1);
check('fix_crops.php without the flag explains --wipe-crops', strpos($text, '--wipe-crops') !== false);
check('fix_crops.php without the flag reports nothing changed', stripos($text, 'Nothing was changed') !== false);
check('fix_crops.php refusal never reaches the database', stripos($text, 'SQLSTATE') === false && stripos($text, 'Database') === false);

exit($fail ? 1 : 0);
