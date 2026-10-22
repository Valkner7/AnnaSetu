<?php
namespace App\Services;

use App\Helpers\PriceAlertRules;
use App\Models\PriceAlert;

/**
 * Checks active price alerts against the latest reported prices.
 * One price lookup per crop+mandi pair, however many alerts watch it.
 * The latest price is the newest point of /history (not the forecast), so an
 * alert reacts to a price a mandi really reported.
 */
class PriceAlertChecker {
    private $alerts;
    private $service;

    public function __construct(PriceAlert $alerts, MandiService $service) {
        $this->alerts = $alerts;
        $this->service = $service;
    }

    /**
     * @param int|null $userId only this user's alerts, or null for everyone's (scheduled run)
     * @return array checked, triggered, unavailable (alerts we could not price), pairs
     */
    public function run(?int $userId = null): array {
        $summary = ['checked' => 0, 'triggered' => 0, 'unavailable' => 0, 'pairs' => 0];
        $byPair = [];
        foreach ($this->alerts->listActive($userId) as $a) {
            $byPair[$a['crop'] . "\n" . $a['mandi']][] = $a;
        }
        foreach ($byPair as $alerts) {
            $summary['pairs']++;
            $res = $this->service->getHistory($alerts[0]['crop'], $alerts[0]['mandi'], 7);
            $pt = $res['ok'] ? PriceAlertRules::latestPoint($res['data']['points'] ?? null) : null;
            // A price this mandi reported weeks ago is not today's price: leave its alerts untouched.
            if ($pt !== null && !PriceAlertRules::isFresh($pt[1])) {
                $pt = null;
            }
            foreach ($alerts as $a) {
                if ($pt === null) {
                    $summary['unavailable']++;
                    continue;
                }
                $summary['checked']++;
                if (PriceAlertRules::isTriggered($a['direction'], $a['target_price'], $pt[0])) {
                    if ($this->alerts->markTriggered((int) $a['id'], $pt[0], $pt[1])) {
                        $summary['triggered']++;
                    }
                } else {
                    $this->alerts->recordCheck((int) $a['id'], $pt[0], $pt[1]);
                }
            }
        }
        return $summary;
    }
}
