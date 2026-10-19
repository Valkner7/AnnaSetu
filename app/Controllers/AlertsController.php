<?php
namespace App\Controllers;

use App\Helpers\MandiText;
use App\Helpers\PriceAlertRules;
use App\Helpers\Translator;
use App\Helpers\Validator;
use App\Models\PriceAlert;
use App\Services\MandiService;
use App\Services\PriceAlertChecker;

require_once __DIR__ . '/../Helpers/Translator.php';
require_once __DIR__ . '/../Helpers/MandiText.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Helpers/PriceAlertRules.php';

/**
 * Price alerts: "tell me when Potato at Rayya reaches Rs X per quintal".
 * Available to every logged-in role. Alerts are shown in the app (nothing is
 * sent by SMS or WhatsApp). All changes are POST (the session CSRF token is
 * checked in public/index.php) and every query is scoped to the user.
 */
class AlertsController {
    protected $userId;

    public function __construct() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id'])) {
            header("Location: " . BASE_URL . "/index.php?url=auth/loginView");
            exit;
        }
        $this->userId = (int) $_SESSION['user_id'];
    }

    /** Overridable so tests can inject fakes. */
    protected function service() { return new MandiService(); }
    protected function alerts() { return new PriceAlert(); }

    public function index() {
        $title = "Price Alerts - SmartHarvest AI";
        $model = $this->alerts();
        $crops = $this->service()->getReliableCrops(false);
        $myAlerts = $model->listByUser($this->userId);
        $newlyReached = 0;
        foreach ($myAlerts as $a) {
            if ($a['status'] === 'triggered' && (int) $a['seen'] === 0) {
                $newlyReached++;
            }
        }
        $model->markAllSeen($this->userId);       // shown highlighted once, then normal
        $maxActive = PriceAlertRules::MAX_ACTIVE_PER_USER;
        $contentView = __DIR__ . '/../../views/mandi/alerts.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    public function create() {
        if (!$this->postOnly()) { return; }
        $crop = $_POST['crop'] ?? null;
        $mandi = $_POST['mandi'] ?? null;
        $direction = Validator::oneOf($_POST['direction'] ?? null, PriceAlertRules::DIRECTIONS);
        $target = Validator::number($_POST['target_price'] ?? null, PriceAlertRules::MIN_PRICE, PriceAlertRules::MAX_PRICE);

        if (!is_string($crop) || trim($crop) === '' || !is_string($mandi) || trim($mandi) === '' || $direction === null) {
            return $this->fail('al_err_input', 400);
        }
        if ($target === null) {
            return $this->fail('al_err_price', 400);
        }
        $crop = trim($crop);
        session_write_close();                        // the price service can be slow
        $svc = $this->service();
        if (!in_array($crop, $svc->getReliableCrops(), true)) {
            return $this->fail('al_err_input', 400);
        }
        $meta = $svc->getMeta($crop);
        if (!$meta['ok']) {
            return $this->fail('al_err_service', 502);
        }
        $canonical = PriceAlertRules::canonicalMandi($mandi, $meta['data']['mandis'] ?? []);
        if ($canonical === null) {
            return $this->fail('al_err_mandi', 400);
        }
        $model = $this->alerts();
        if ($model->countActive($this->userId) >= PriceAlertRules::MAX_ACTIVE_PER_USER) {
            return $this->fail('al_err_limit', 400, ['n' => PriceAlertRules::MAX_ACTIVE_PER_USER]);
        }
        if ($model->exists($this->userId, $crop, $canonical, $direction, $target)) {
            return $this->fail('al_err_dup', 400);
        }
        $id = $model->create($this->userId, $crop, $canonical, $direction, $target);
        $this->json(['ok' => true, 'id' => $id]);
    }

    public function delete() {
        if (!$this->postOnly()) { return; }
        $id = Validator::id($_POST['id'] ?? null);
        if ($id === null || !$this->alerts()->delete($id, $this->userId)) {
            return $this->fail('al_err_notfound', 404);
        }
        $this->json(['ok' => true]);
    }

    public function rearm() {
        if (!$this->postOnly()) { return; }
        $id = Validator::id($_POST['id'] ?? null);
        if ($id === null) {
            return $this->fail('al_err_notfound', 404);
        }
        $model = $this->alerts();
        if ($model->countActive($this->userId) >= PriceAlertRules::MAX_ACTIVE_PER_USER) {
            return $this->fail('al_err_limit', 400, ['n' => PriceAlertRules::MAX_ACTIVE_PER_USER]);
        }
        if (!$model->rearm($id, $this->userId)) {
            return $this->fail('al_err_notfound', 404);
        }
        $this->json(['ok' => true]);
    }

    /** "Check now": run this user's alerts against the latest prices. */
    public function check() {
        if (!$this->postOnly()) { return; }
        session_write_close();
        $summary = (new PriceAlertChecker($this->alerts(), $this->service()))->run($this->userId);
        $this->json(['ok' => true, 'data' => $summary]);
    }

    private function postOnly(): bool {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Allow: POST');
            $this->json(['ok' => false, 'error' => 'Method not allowed'], 405);
            return false;
        }
        return true;
    }

    private function fail(string $key, int $code, array $vars = []) {
        $this->json(['ok' => false, 'error' => MandiText::fill(Translator::get($key), $vars)], $code);
    }

    private function json($payload, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
