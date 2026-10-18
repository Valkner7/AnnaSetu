<?php
// Price-service settings (M8). Defaults live in App\Helpers\MandiConfig.
//
// To point AnnaSetu at a different service address, use ONE of:
//   - the environment variable MANDI_API_BASE (e.g. Apache: SetEnv MANDI_API_BASE https://host), or
//   - a local file config/mandi.local.php (copy config/mandi.local.example.php; it is git-ignored).
//
// Shared secret for the advisory endpoints (M10): MANDI_API_SECRET, or 'api_secret' in
// mandi.local.php. Must equal API_SHARED_SECRET on the price service. Optional.
require_once __DIR__ . '/../app/Helpers/MandiConfig.php';

$env = getenv('MANDI_API_BASE');
if (!is_string($env) || $env === '') {
    $env = $_SERVER['MANDI_API_BASE'] ?? null;   // Apache SetEnv can land here instead
}

$secret = getenv('MANDI_API_SECRET');
if (!is_string($secret) || $secret === '') {
    $secret = $_SERVER['MANDI_API_SECRET'] ?? null;
}

return \App\Helpers\MandiConfig::load(
    __DIR__ . '/mandi.local.php',
    is_string($env) ? $env : null,
    is_string($secret) ? $secret : null
);
