<?php
namespace App\Controllers;

use App\Services\MandiService;

class MandiController {
    private $reliableCrops = ['Potato', 'Onion', 'Tomato'];

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . BASE_URL . "/index.php?url=auth/loginView");
            exit;
        }
    }

    public function index() {
        $title = "Market Prices - SmartHarvest AI";
        $crops = $this->reliableCrops;
        $contentView = __DIR__ . '/../../views/mandi/prices.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    public function mandis() {
        $crop = $_GET['crop'] ?? '';
        if (!in_array($crop, $this->reliableCrops, true)) {
            $this->json(['ok' => false, 'error' => 'Unsupported crop'], 400);
            return;
        }
        session_write_close();
        $res = (new MandiService())->getMeta($crop);
        $this->json([
            'ok' => $res['ok'],
            'error' => $res['error'],
            'mandis' => $res['data']['mandis'] ?? [],
        ], $res['ok'] ? 200 : 502);
    }

    public function forecast() {
        $crop = $_GET['crop'] ?? '';
        $mandi = trim($_GET['mandi'] ?? '');
        if (!in_array($crop, $this->reliableCrops, true) || $mandi === '') {
            $this->json(['ok' => false, 'error' => 'Choose a crop and a mandi'], 400);
            return;
        }
        session_write_close();
        $res = (new MandiService())->getPredict($crop, $mandi);
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
        ]]);
    }

    private function json($payload, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
