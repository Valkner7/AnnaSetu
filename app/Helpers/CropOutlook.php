<?php
namespace App\Helpers;

/**
 * Turns a crop's past daily prices into a few plain numbers for the Crop
 * Planning page (F2). It only describes what already happened. It does not
 * forecast: the price model looks 7 days ahead and cannot support a revenue
 * figure for a crop sown months from now.
 */
class CropOutlook {

    public const MIN_POINTS = 10;       // fewer price days than this: say "not enough data"
    public const SWING_LOW = 0.08;      // price swing = std dev / mean over the last 90 days
    public const SWING_MEDIUM = 0.15;   // below LOW = low, below MEDIUM = medium, else high

    /**
     * @param array  $points list of ['date' => 'YYYY-MM-DD', 'price' => number]
     * @param string $today  'YYYY-MM-DD' (injectable for tests)
     * @return array status 'ok' or 'no_data', plus the figures below when ok
     */
    public static function summarize(array $points, string $today): array {
        $clean = [];
        foreach ($points as $p) {
            if (!is_array($p) || !isset($p['date'], $p['price']) || !is_string($p['date']) || !is_numeric($p['price'])) {
                continue;
            }
            $ts = strtotime($p['date'] . ' UTC');
            $price = (float) $p['price'];
            if ($ts === false || $price <= 0) {
                continue;
            }
            $clean[$ts] = $price;           // one price per day; later rows win
        }
        ksort($clean);
        if (count($clean) < self::MIN_POINTS) {
            return ['status' => 'no_data'];
        }

        $times = array_keys($clean);
        $latestTs = end($times);
        $latest = $clean[$latestTs];
        $todayTs = strtotime($today . ' UTC');
        $day = 86400;

        $last30 = self::valuesBetween($clean, $latestTs - 29 * $day, $latestTs);
        $last90 = self::valuesBetween($clean, $latestTs - 89 * $day, $latestTs);
        $before = self::valuesBetween($clean, $latestTs - 104 * $day, $latestTs - 75 * $day);   // about 90 days earlier
        $avg30 = self::mean($last30);
        $avgAll = self::mean(array_values($clean));

        $change90 = null;
        if (count($before) >= 3 && count($last30) >= 3) {
            $change90 = round((self::mean($last30) - self::mean($before)) / self::mean($before) * 100, 1);
        }

        $swing = null;
        if (count($last90) >= self::MIN_POINTS) {
            $cv = self::stdev($last90) / self::mean($last90);
            $swing = $cv < self::SWING_LOW ? 'low' : ($cv < self::SWING_MEDIUM ? 'medium' : 'high');
        }

        return [
            'status' => 'ok',
            'latest_price' => round($latest, 2),
            'latest_date' => gmdate('Y-m-d', $latestTs),
            'age_days' => $todayTs === false ? null : max(0, (int) floor(($todayTs - $latestTs) / $day)),
            'avg_30' => round($avg30, 2),
            'avg_period' => round($avgAll, 2),
            'days_covered' => (int) round(($latestTs - $times[0]) / $day) + 1,
            'vs_period_pct' => round(($avg30 - $avgAll) / $avgAll * 100, 1),
            'change_90_pct' => $change90,
            'swing' => $swing,
        ];
    }

    private static function valuesBetween(array $byTs, int $from, int $to): array {
        $out = [];
        foreach ($byTs as $ts => $v) {
            if ($ts >= $from && $ts <= $to) {
                $out[] = $v;
            }
        }
        return $out;
    }

    private static function mean(array $v): float {
        return array_sum($v) / count($v);
    }

    private static function stdev(array $v): float {
        $m = self::mean($v);
        $s = 0.0;
        foreach ($v as $x) {
            $s += ($x - $m) ** 2;
        }
        return sqrt($s / count($v));
    }
}
