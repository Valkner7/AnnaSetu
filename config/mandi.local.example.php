<?php
// Copy this file to config/mandi.local.php to override the price-service settings
// on this computer only. mandi.local.php is git-ignored, so it is never committed.
// Delete any line you do not want to change.
return [
    // Address of the deployed price service (https, or http only for localhost).
    'api_base' => 'https://mandi-price-forecast-1.onrender.com',
    // 'timeout_seconds' => 90,
    // 'connect_timeout_seconds' => 20,
    // 'cache_ttl_seconds' => 14400,
    // Same value as API_SHARED_SECRET on the price service (8-200 visible characters, no spaces).
    // Only sent to the advisory endpoints. Never commit it.
    // 'api_secret' => '',
];
