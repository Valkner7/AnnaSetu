<?php
namespace App\Controllers;

use App\Services\MandiService;
use App\Helpers\MandiText;
use App\Helpers\Translator;

require_once __DIR__ . '/../Helpers/Translator.php';
require_once __DIR__ . '/../Helpers/MandiText.php';

class MandiController {

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . BASE_URL . "/index.php?url=auth/loginView");
            exit;
        }
    }

    /** Overridable so tests can inject a fake price service. */
    protected function service() {
        return new MandiService();
    }

    /**
     * Crops we are willing to ask the price service about (M9). The service's
     * /meta does not reject unknown crops itself, so this check stays here.
     * The page render passes false: cache only, never wait on a cold start.
     */
    protected function allowedCrops($network = true) {
        return $this->service()->getReliableCrops($network);
    }

    public function index() {
        $title = "Market Prices - SmartHarvest AI";
        $crops = $this->allowedCrops(false);
        $contentView = __DIR__ . '/../../views/mandi/prices.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    public function mandis() {
        $crop = $_GET['crop'] ?? '';
        if (!is_string($crop) || $crop === '') {
            $this->json(['ok' => false, 'error' => 'Unsupported crop'], 400);
            return;
        }
        session_write_close();
        if (!in_array($crop, $this->allowedCrops(), true)) {
            $this->json(['ok' => false, 'error' => 'Unsupported crop'], 400);
            return;
        }
        $res = $this->service()->getMeta($crop);
        $this->json([
            'ok' => $res['ok'],
            'error' => $res['error'],
            'mandis' => $res['data']['mandis'] ?? [],
        ], $res['ok'] ? 200 : 502);
    }

    public function forecast() {
        $crop = $_GET['crop'] ?? '';
        $mandi = is_string($_GET['mandi'] ?? null) ? trim($_GET['mandi']) : '';
        if (!is_string($crop) || $crop === '' || $mandi === '') {
            $this->json(['ok' => false, 'error' => 'Choose a crop and a mandi'], 400);
            return;
        }
        session_write_close();
        if (!in_array($crop, $this->allowedCrops(), true)) {
            $this->json(['ok' => false, 'error' => 'Choose a crop and a mandi'], 400);
            return;
        }
        $res = $this->service()->getPredict($crop, $mandi);
        if (!$res['ok']) {
            $msg = ($res['status'] === 404 || $res['status'] === 422)
                ? 'No forecast is available for this crop and mandi yet.'
                : $res['error'];
            $this->json(['ok' => false, 'error' => $msg, 'upstream_status' => $res['status']], 502);
            return;
        }
        $d = $res['data'];
        $payload = [
            'crop' => $d['crop'] ?? $crop,
            'mandi' => $d['mandi'] ?? $mandi,
            'latest_date' => $d['latest_date'] ?? null,
            'latest_price' => $d['latest_price'] ?? null,
            'trend' => $d['trend'] ?? null,
            'unit' => $d['unit'] ?? 'INR per quintal',
            'forecast' => $d['forecast'] ?? [],
            'model' => $d['model'] ?? null,
            'confidence_note' => $d['confidence']['note'] ?? null,
            'data_note' => $d['data_note'] ?? null,
            'data_age_warning' => $d['data_age_warning'] ?? null,
            'data_age_days' => $d['data_age_days'] ?? null,
            'model_note' => $d['model_note'] ?? null,
            'served_stale' => !empty($res['stale']),
            'dataset_latest_date' => $d['dataset_latest_date'] ?? null,
        ];
        // Trend, confidence note and data-age notices in the user's language (en, hi, pa).
        $lang = Translator::getCurrentLang();
        $payload = MandiText::localize($payload, $lang, function ($k) { return Translator::get($k); });
        $this->json(['ok' => true, 'data' => $payload]);
    }

    /** Past daily prices for the chart (F1). Same crop whitelist and error handling as forecast(). */
    public function history() {
        $crop = $_GET['crop'] ?? '';
        $mandi = is_string($_GET['mandi'] ?? null) ? trim($_GET['mandi']) : '';
        $days = (isset($_GET['days']) && is_scalar($_GET['days'])) ? (int) $_GET['days'] : 60;
        $days = max(7, min(365, $days));
        if (!is_string($crop) || $crop === '' || $mandi === '') {
            $this->json(['ok' => false, 'error' => 'Choose a crop and a mandi'], 400);
            return;
        }
        session_write_close();
        if (!in_array($crop, $this->allowedCrops(), true)) {
            $this->json(['ok' => false, 'error' => 'Choose a crop and a mandi'], 400);
            return;
        }
        $res = $this->service()->getHistory($crop, $mandi, $days);
        if (!$res['ok']) {
            $this->json(['ok' => false, 'error' => 'Price history is not available for this selection.',
                         'upstream_status' => $res['status']], 502);
            return;
        }
        $points = [];
        foreach (($res['data']['points'] ?? []) as $pt) {
            if (is_array($pt) && isset($pt['date'], $pt['price']) && is_string($pt['date']) && is_numeric($pt['price'])) {
                $points[] = ['date' => $pt['date'], 'price' => (float) $pt['price']];
            }
        }
        $this->json(['ok' => true, 'data' => [
            'crop' => $res['data']['crop'] ?? $crop,
            'mandi' => $res['data']['mandi'] ?? $mandi,
            'unit' => $res['data']['unit'] ?? 'INR per quintal',
            'points' => $points,
            'served_stale' => !empty($res['stale']),
        ]]);
    }

    // --- COMPARE MANDIS (F5) ---

    public function compare() {
        $title = "Compare Mandis - SmartHarvest AI";
        $crops = $this->allowedCrops(false);
        $contentView = __DIR__ . '/../../views/mandi/compare.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    /**
     * 2 to 5 distinct, plain mandi names from a comma-separated string, or
     * null if the input is not usable. Names are only ever passed on to the
     * price service, never used in a path or query on our side.
     */
    public static function parseMandiNames($raw) {
        if (!is_string($raw) || $raw === '' || strlen($raw) > 600) {
            return null;
        }
        $seen = [];
        $out = [];
        foreach (explode(',', $raw) as $name) {
            $name = trim($name);
            if ($name === '' || strlen($name) > 80 || preg_match('/[\x00-\x1F\x7F<>]/', $name)) {
                return null;
            }
            $key = strtolower($name);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $name;
            }
        }
        return (count($out) >= 2 && count($out) <= 5) ? $out : null;
    }

    /**
     * A latitude/longitude pair inside the rough box around India, rounded to
     * 2 decimals (about 1 km) so the app never sends or handles more precision
     * than it needs. Null if either value is missing, not a number, or outside.
     */
    public static function parseLocation($lat, $lon) {
        foreach ([$lat, $lon] as $v) {
            if (!is_string($v) || !preg_match('/^-?\d{1,3}(\.\d{1,10})?$/', $v)) {
                return null;
            }
        }
        $lat = round((float) $lat, 2);
        $lon = round((float) $lon, 2);
        if ($lat < 6 || $lat > 38 || $lon < 68 || $lon > 98) {
            return null;
        }
        return [$lat, $lon];
    }

    /** Nearest mandis that have a price for the crop, for "use my location" (F4). */
    public function nearby() {
        $crop = $_GET['crop'] ?? '';
        $loc = self::parseLocation($_GET['lat'] ?? null, $_GET['lon'] ?? null);
        if (!is_string($crop) || $crop === '' || $loc === null) {
            $this->json(['ok' => false, 'error' => 'Choose a crop and share a location in India'], 400);
            return;
        }
        session_write_close();
        if (!in_array($crop, $this->allowedCrops(), true)) {
            $this->json(['ok' => false, 'error' => 'Choose a crop and share a location in India'], 400);
            return;
        }
        $res = $this->service()->getNearby($loc[0], $loc[1], $crop, 15);
        if (!$res['ok']) {
            $this->json(['ok' => false, 'error' => 'Nearby mandis are not available right now.',
                         'upstream_status' => $res['status']], 502);
            return;
        }
        $rows = [];
        foreach (($res['data']['mandis'] ?? []) as $m) {
            // Only mandis that actually have a price for this crop are useful for comparing.
            if (!is_array($m) || !is_string($m['mandi'] ?? null) || !is_numeric($m['distance_km'] ?? null) || !is_numeric($m['latest_price'] ?? null)) {
                continue;
            }
            $rows[] = [
                'mandi' => $m['mandi'],
                'district' => is_string($m['district'] ?? null) ? $m['district'] : '',
                'distance_km' => round((float) $m['distance_km'], 1),
                'latest_price' => (float) $m['latest_price'],
            ];
            if (count($rows) === 5) {
                break;
            }
        }
        usort($rows, function ($a, $b) { return $a['distance_km'] <=> $b['distance_km']; });
        $this->json(['ok' => true, 'data' => ['crop' => $crop, 'mandis' => $rows]]);
    }

    /** Ranked comparison of one crop across mandis, for the compare page. */
    public function comparison() {
        $crop = $_GET['crop'] ?? '';
        $names = self::parseMandiNames($_GET['mandis'] ?? null);
        if (!is_string($crop) || $crop === '' || $names === null) {
            $this->json(['ok' => false, 'error' => 'Choose a crop and 2 to 5 mandis'], 400);
            return;
        }
        session_write_close();
        if (!in_array($crop, $this->allowedCrops(), true)) {
            $this->json(['ok' => false, 'error' => 'Choose a crop and 2 to 5 mandis'], 400);
            return;
        }
        $res = $this->service()->getCompare($crop, $names);
        if (!$res['ok']) {
            $msg = ($res['status'] === 404 || $res['status'] === 422)
                ? 'No comparison is available for these mandis yet.'
                : $res['error'];
            $this->json(['ok' => false, 'error' => $msg, 'upstream_status' => $res['status']], 502);
            return;
        }
        $d = $res['data'];
        $lang = Translator::getCurrentLang();
        $t = function ($k) { return Translator::get($k); };
        $rows = [];
        foreach (($d['results'] ?? []) as $r) {
            if (!is_array($r) || !isset($r['mandi'], $r['latest_price']) || !is_string($r['mandi']) || !is_numeric($r['latest_price'])) {
                continue;
            }
            $row = MandiText::localize([
                'trend' => is_string($r['trend'] ?? null) ? $r['trend'] : '',
                'latest_date' => is_string($r['latest_date'] ?? null) ? $r['latest_date'] : null,
            ], $lang, $t);
            $rows[] = [
                'mandi' => $r['mandi'],
                'latest_price' => (float) $r['latest_price'],
                'latest_date' => $row['latest_date'],
                'age_days' => MandiText::daysSince($row['latest_date']),
                'trend_label' => $row['trend_label'],
            ];
        }
        if (count($rows) === 0) {
            $this->json(['ok' => false, 'error' => 'No comparison is available for these mandis yet.'], 502);
            return;
        }
        // Our own ranking from the cleaned rows: highest price first.
        usort($rows, function ($a, $b) { return $b['latest_price'] <=> $a['latest_price']; });
        $best = $rows[0];
        $worst = $rows[count($rows) - 1];
        $skipped = [];
        foreach (($d['skipped'] ?? []) as $s) {
            if (is_array($s) && is_string($s['mandi'] ?? null)) {
                $skipped[] = $s['mandi'];
            }
        }
        $dates = array_filter(array_column($rows, 'latest_date'));
        $spreadDays = 0;
        if (count($dates) > 1) {
            $spreadDays = (int) round((strtotime(max($dates) . ' UTC') - strtotime(min($dates) . ' UTC')) / 86400);
        }
        $this->json(['ok' => true, 'data' => [
            'crop' => is_string($d['crop'] ?? null) ? $d['crop'] : $crop,
            'unit' => 'INR per quintal',
            'rows' => $rows,
            'best_mandi' => $best['mandi'],
            'spread' => round($best['latest_price'] - $worst['latest_price'], 2),
            'spread_pct' => $worst['latest_price'] > 0 ? round(($best['latest_price'] - $worst['latest_price']) / $worst['latest_price'] * 100, 1) : 0,
            'date_spread_days' => $spreadDays,
            'skipped' => $skipped,
            'served_stale' => !empty($res['stale']),
        ]]);
    }

    private function json($payload, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
