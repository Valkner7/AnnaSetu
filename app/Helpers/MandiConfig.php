<?php
namespace App\Helpers;

/**
 * Loads the price-service settings (M8).
 *
 * Order of precedence for the service address (highest first):
 *   1. environment variable MANDI_API_BASE
 *   2. config/mandi.local.php (git-ignored, for XAMPP where setting
 *      environment variables is awkward)
 *   3. the default below
 *
 * An address that is not usable is ignored with a log line, so a typo can
 * never point the app at something unexpected: the next source is used.
 */
class MandiConfig {

    public const DEFAULT_API_BASE = 'https://mandi-price-forecast-1.onrender.com';

    // Only these keys may be overridden from the local file.
    private const KEYS = ['api_base', 'timeout_seconds', 'connect_timeout_seconds', 'cache_ttl_seconds'];

    /**
     * @param string      $localFile Path to the optional local override file.
     * @param string|null $envValue  Value of MANDI_API_BASE (null/'' = not set).
     */
    public static function load(string $localFile, ?string $envValue = null): array {
        $cfg = [
            'api_base' => self::DEFAULT_API_BASE,
            'timeout_seconds' => 90,
            'connect_timeout_seconds' => 20,
            // Cache upstream answers for 4 hours (prices change once a day). The last
            // good copy is still served, marked stale, if the service is down.
            'cache_ttl_seconds' => 14400,
        ];

        if (is_file($localFile)) {
            $local = (static function ($file) { return require $file; })($localFile);
            if (is_array($local)) {
                foreach (self::KEYS as $k) {
                    if (!array_key_exists($k, $local)) {
                        continue;
                    }
                    if ($k === 'api_base') {
                        $clean = self::cleanBase($local[$k]);
                        if ($clean === null) {
                            error_log('MandiConfig: ignoring unusable api_base in ' . basename($localFile));
                            continue;
                        }
                        $cfg[$k] = $clean;
                    } elseif (is_int($local[$k]) && $local[$k] >= 0) {
                        $cfg[$k] = $local[$k];
                    }
                }
            }
        }

        if ($envValue !== null && trim($envValue) !== '') {
            $clean = self::cleanBase($envValue);
            if ($clean === null) {
                error_log('MandiConfig: ignoring unusable MANDI_API_BASE');
            } else {
                $cfg['api_base'] = $clean;
            }
        }
        return $cfg;
    }

    /**
     * Returns the address without a trailing slash, or null if it is not safe
     * to use. Rules: https (plain http only for this computer: localhost,
     * 127.0.0.1, ::1), a host, no user:password, no query string or fragment.
     */
    public static function cleanBase($value): ?string {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x20\x7F"\'<>\\\\]/', $value)) {
            return null;
        }
        $p = parse_url($value);
        if ($p === false || empty($p['scheme']) || empty($p['host'])) {
            return null;
        }
        if (isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) {
            return null;
        }
        $scheme = strtolower($p['scheme']);
        $host = strtolower(trim($p['host'], '[]'));
        $isLoopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if ($scheme !== 'https' && !($scheme === 'http' && $isLoopback)) {
            return null;
        }
        return rtrim($value, '/');
    }
}
