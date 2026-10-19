<?php
namespace App\Helpers;

use App\Models\PriceAlert;

/**
 * Numbers for the dashboard "Market Prices" card. Never throws: if the alerts
 * table is missing (run_schema7.php not run yet) or MySQL has a problem, the
 * card still shows its links and just leaves the alert line out.
 */
class MarketCard {

    /** @return array available (bool), unseen, active (ints), recent (array of up to 3 triggered alerts) */
    public static function summary(?PriceAlert $alerts, int $userId): array {
        $out = ['available' => false, 'unseen' => 0, 'active' => 0, 'recent' => []];
        if ($alerts === null) { return $out; }
        try {
            $out['unseen'] = $alerts->countUnseen($userId);
            $out['active'] = $alerts->countActive($userId);
            $out['recent'] = $alerts->recentTriggered($userId, 3);
            $out['available'] = true;
        } catch (\Throwable $e) {
            error_log('MarketCard: alert counts unavailable: ' . $e->getMessage());
            return ['available' => false, 'unseen' => 0, 'active' => 0, 'recent' => []];
        }
        return $out;
    }

    /** One line about the user's alerts, or '' when there is nothing to say. $t translates a key. */
    public static function alertLine(array $s, callable $t): string {
        if (empty($s['available'])) { return ''; }
        if ($s['unseen'] > 0) { return MandiText::fill($t('dm_alerts_reached'), ['n' => $s['unseen']]); }
        if ($s['active'] > 0) { return MandiText::fill($t('dm_alerts_watching'), ['n' => $s['active']]); }
        return $t('dm_no_alerts');
    }
}
