<?php
return [
    'api_base' => 'https://mandi-price-forecast-1.onrender.com',
    'timeout_seconds' => 90,
    'connect_timeout_seconds' => 20,
    // Cache upstream answers for 4 hours (prices change once a day). Last good
    // copy is still served, marked stale, if the service is down.
    'cache_ttl_seconds' => 14400,
];
