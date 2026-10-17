<?php
// Price-service settings (M8). Defaults live in App\Helpers\MandiConfig.
//
// To point AnnaSetu at a different service address, use ONE of:
//   - the environment variable MANDI_API_BASE (e.g. Apache: SetEnv MANDI_API_BASE https://host), or
//   - a local file config/mandi.local.php (copy config/mandi.local.example.php; it is git-ignored).
require_once __DIR__ . '/../app/Helpers/MandiConfig.php';

$env = getenv('MANDI_API_BASE');
if (!is_string($env) || $env === '') {
    $env = $_SERVER['MANDI_API_BASE'] ?? null;   // Apache SetEnv can land here instead
}

return \App\Helpers\MandiConfig::load(__DIR__ . '/mandi.local.php', is_string($env) ? $env : null);
