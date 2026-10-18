<?php
namespace App\Controllers;

use App\Services\MandiService;

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
        $this->json(['ok' => true, 'data' => [
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
        ]]);
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

    private function json($payload, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
