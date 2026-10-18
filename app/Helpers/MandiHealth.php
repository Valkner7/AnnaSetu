<?php
namespace App\Helpers;

/**
 * Turns the price service's /status answer into one small, safe summary for the
 * health badge. Pure function: no network, no session.
 *
 * Reasons the service lists that AnnaSetu does not care about are ignored:
 * 'subscriptions_db_init_failed' only means the service's own WhatsApp alert
 * database (Turso) is not set up, and AnnaSetu keeps its price alerts in MySQL.
 */
class MandiHealth {

    public const IGNORED_REASONS = ['subscriptions_db_init_failed'];

    /**
     * @param array|null $statusData decoded /status body, or null if the service did not answer
     * @param string     $via        'local' or 'hosted' (which address answered)
     * @return array level: ok|stale|degraded|down, plus source, latest_date, days_stale, model_loaded
     */
    public static function summarize(?array $statusData, string $via = 'local'): array {
        $base = ['level' => 'down', 'source' => ($via === 'hosted' ? 'hosted' : 'local'),
                 'latest_date' => null, 'days_stale' => null, 'model_loaded' => false];
        if ($statusData === null || !isset($statusData['status'])) {
            return $base;
        }
        $data = is_array($statusData['data'] ?? null) ? $statusData['data'] : [];
        $model = is_array($statusData['model'] ?? null) ? $statusData['model'] : [];

        $date = $data['latest_date'] ?? null;
        $base['latest_date'] = (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : null;
        $days = $data['days_stale'] ?? null;
        $base['days_stale'] = (is_int($days) || (is_string($days) && ctype_digit($days))) ? (int) $days : null;
        $base['model_loaded'] = !empty($model['artifact_loaded']);

        $reasons = [];
        foreach ((is_array($statusData['reasons'] ?? null) ? $statusData['reasons'] : []) as $r) {
            if (is_string($r) && !in_array($r, self::IGNORED_REASONS, true)) {
                $reasons[] = $r;
            }
        }
        if (!$reasons) {
            $base['level'] = 'ok';
        } elseif ($reasons === ['data_stale']) {
            $base['level'] = 'stale';
        } else {
            $base['level'] = 'degraded';
        }
        return $base;
    }
}
