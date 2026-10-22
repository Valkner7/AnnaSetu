<?php
// Copy this file to config/mandi.local.php to override the price-service settings
// on this computer only. mandi.local.php is git-ignored, so it is never committed.
// Delete any line you do not want to change.
return [
    // Main address of the price service (https, or http only for localhost).
    // Default: the bundled service on this computer (tools\start-mandi-service.ps1).
    // 'api_base' => 'http://127.0.0.1:8001',
    // Used only when the main address cannot be reached. Default: the hosted copy.
    // Use '' to turn the fallback off.
    // 'fallback_base' => 'https://mandi-price-forecast-1.onrender.com',
    // 'timeout_seconds' => 90,
    // 'connect_timeout_seconds' => 20,
    // 'cache_ttl_seconds' => 14400,
    // Same value as API_SHARED_SECRET on the price service (8-200 visible characters, no spaces).
    // Only sent to the advisory endpoints. Never commit it.
    // 'api_secret' => '',
    // Secret of the hosted fallback (its own API_SHARED_SECRET on Render). Sent only to
    // 'fallback_base', only to the advisory endpoints. Without it the hosted copy cannot
    // answer advisory requests when its secret is set (or when it is unset and the caller
    // is remote). Different from api_secret. Never commit it.
    // 'fallback_secret' => '',
];
