<?php
// Run: php tests/check_validator.php   (exit 0 = pass)
require __DIR__ . '/../app/Helpers/Validator.php';
use App\Helpers\Validator as V;
$cases = [
  ['number "5"',        V::number("5", 1, 10) === 5.0],
  ['number negative',   V::number("-3", 0.01, 10) === null],
  ['number zero',       V::number(0, 0.01, 10) === null],
  ['number too big',    V::number("1e9", 1, 10) === null],
  ['number text',       V::number("abc", 1, 10) === null],
  ['number array',      V::number([1], 1, 10) === null],
  ['id ok',             V::id("12") === 12],
  ['id zero',           V::id("0") === null],
  ['id junk',           V::id("1 OR 1=1") === null],
  ['date ok',           V::date("2030-02-28") === "2030-02-28"],
  ['date fake',         V::date("2030-02-31") === null],
  ['date past blocked', V::date("2001-01-01", true) === null],
  ['text empty',        V::text("   ", 5) === null],
  ['text too long',     V::text("abcdef", 5) === null],
  ['oneOf bad',         V::oneOf("admin", ["a","b"]) === null],
];
$fail = 0;
foreach ($cases as [$name, $ok]) { echo ($ok ? "PASS " : "FAIL ") . $name . "\n"; $fail += $ok ? 0 : 1; }
exit($fail ? 1 : 0);
