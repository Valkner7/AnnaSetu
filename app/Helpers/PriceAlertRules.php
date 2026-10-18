<?php
namespace App\Helpers;

/**
 * Rules for price alerts. Pure functions (no database, no network) so they can be tested alone.
 */
class PriceAlertRules {

    public const DIRECTIONS = ['above', 'below'];
    public const MAX_ACTIVE_PER_USER = 20;
    public const MIN_PRICE = 1;
    public const MAX_PRICE = 200000;     // Rs per quintal

    /** True when the reported price has reached the alert's target. Equal counts as reached. */
    public static function isTriggered(string $direction, $target, $price): bool {
        if (!is_numeric($target) || !is_numeric($price) || (float) $price <= 0) {
            return false;
        }
        if ($direction === 'above') {
            return (float) $price >= (float) $target;
        }
        if ($direction === 'below') {
            return (float) $price <= (float) $target;
        }
        return false;
    }

    /**
     * Latest usable point from the service's /history points: the newest entry
     * with a real date and a positive number. Returns [price, date] or null.
     */
    public static function latestPoint($points): ?array {
        if (!is_array($points)) {
            return null;
        }
        $best = null;
        foreach ($points as $p) {
            if (!is_array($p) || !is_string($p['date'] ?? null) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $p['date']) || !is_numeric($p['price'] ?? null) || (float) $p['price'] <= 0) {
                continue;
            }
            if ($best === null || strcmp($p['date'], $best[1]) > 0) {
                $best = [(float) $p['price'], $p['date']];
            }
        }
        return $best;
    }

    /** The mandi name exactly as the service spells it (case-insensitive match), or null. */
    public static function canonicalMandi($name, $mandis): ?string {
        if (!is_string($name) || !is_array($mandis)) {
            return null;
        }
        $name = trim($name);
        foreach ($mandis as $m) {
            if (is_string($m) && strcasecmp(trim($m), $name) === 0) {
                return trim($m);
            }
        }
        return null;
    }
}
