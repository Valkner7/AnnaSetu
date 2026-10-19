<?php
namespace App\Controllers;

use App\Helpers\MandiText;
use App\Helpers\PriceAlertRules;
use App\Helpers\RateLimit;
use App\Helpers\Translator;
use App\Helpers\Validator;
use App\Services\MandiService;

require_once __DIR__ . '/../Helpers/Translator.php';
require_once __DIR__ . '/../Helpers/MandiText.php';
require_once __DIR__ . '/../Helpers/Validator.php';
require_once __DIR__ . '/../Helpers/PriceAlertRules.php';
require_once __DIR__ . '/../Helpers/RateLimit.php';
require_once __DIR__ . '/MandiController.php';

/**
 * "Ask Mandi Setu" (F6): a typed or spoken question about prices, answered from
 * the price service's data (Gemini writes the wording when a key is set,
 * otherwise a plain summary is shown). Every call is POST (CSRF-checked in
 * public/index.php), limited per user here because the price service sees all
 * AnnaSetu users as one address, and the shared secret (M10) is added by
 * MandiService on the server, never sent to the browser.
 */
class AdvisoryController {
    public const MAX_QUESTION = 500;
    public const LIMIT_CALLS = 12;          // per user ...
    public const LIMIT_WINDOW = 600;        // ... per 10 minutes
    public const LANGS = ['en', 'hi', 'pa'];
    private const MAX_AUDIO_BYTES = 5000000;

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
    protected function limiter() { return new RateLimit(); }

    public function index() {
        $title = "Ask Mandi Setu - SmartHarvest AI";
        $crops = $this->service()->getReliableCrops(false);
        $maxQuestion = self::MAX_QUESTION;
        $uiLang = Translator::getCurrentLang();
        $contentView = __DIR__ . '/../../views/mandi/advisory.php';
        require_once __DIR__ . '/../../views/layouts/main.php';
    }

    /** Written answer for one mandi (mode=one) or a comparison of 2 to 5 mandis (mode=compare). */
    public function ask() {
        if (!$this->postOnly()) { return; }
        $in = $this->readQuestion();
        if ($in === null) { return; }
        list($question, $lang) = $in;

        $mode = Validator::oneOf($_POST['mode'] ?? 'one', ['one', 'compare']);
        $crop = is_string($_POST['crop'] ?? null) ? trim($_POST['crop']) : '';
        if ($mode === null || $crop === '') {
            return $this->fail($mode === 'compare' ? 'ad_err_pick_many' : 'ad_err_pick', 400);
        }
        $names = null;
        $one = '';
        if ($mode === 'compare') {
            $names = MandiController::parseMandiNames($_POST['mandis'] ?? null);
            if ($names === null) { return $this->fail('ad_err_pick_many', 400); }
        } else {
            $one = is_string($_POST['mandi'] ?? null) ? trim($_POST['mandi']) : '';
            if ($one === '') { return $this->fail('ad_err_pick', 400); }
        }

        session_write_close();
        $svc = $this->service();
        if (!in_array($crop, $svc->getReliableCrops(), true)) {
            return $this->fail('ad_err_pick', 400);
        }
        $meta = $svc->getMeta($crop);
        if (!$meta['ok']) { return $this->fail('ad_err_service', 502); }
        $known = $meta['data']['mandis'] ?? [];
        if ($mode === 'compare') {
            $canon = [];
            foreach ($names as $n) {
                $c = PriceAlertRules::canonicalMandi($n, $known);
                if ($c === null) { return $this->fail('ad_err_pick_many', 400); }
                $canon[$c] = true;
            }
            $names = array_keys($canon);
            if (count($names) < 2) { return $this->fail('ad_err_pick_many', 400); }
        } else {
            $one = PriceAlertRules::canonicalMandi($one, $known);
            if ($one === null) { return $this->fail('ad_err_pick', 400); }
        }

        if (!$this->limiter()->hit('advisory', $this->userId, self::LIMIT_CALLS, self::LIMIT_WINDOW)) {
            return $this->fail('ad_err_limit', 429);
        }
        $res = ($mode === 'compare')
            ? $svc->getCompareAdvisory($crop, $names, $question, $lang)
            : $svc->getAdvisory($crop, $one, $question, $lang);
        if (!$res['ok']) {
            return $this->serviceFailure($res);
        }
        $answer = $res['data']['advisory'] ?? null;
        if (!is_string($answer) || trim($answer) === '') {
            return $this->fail('ad_err_service', 502);
        }
        $this->json(['ok' => true, 'data' => [
            'answer' => $this->clip($answer, 2000),
            'source' => (($res['data']['advisory_source'] ?? '') === 'gemini') ? 'gemini' : 'fallback',
            'language' => $lang,
        ]]);
    }

    /** Spoken answer as MP3. crop and mandi (if chosen) are put in front of the question so the service finds them. */
    public function speak() {
        if (!$this->postOnly()) { return; }
        $in = $this->readQuestion();
        if ($in === null) { return; }
        list($question, $lang) = $in;
        $crop = is_string($_POST['crop'] ?? null) ? trim($_POST['crop']) : '';
        $mandi = is_string($_POST['mandi'] ?? null) ? trim($_POST['mandi']) : '';
        if (Validator::length($crop) > 60 || Validator::length($mandi) > 80 || preg_match('/[\x00-\x1F\x7F<>]/', $crop . $mandi)) {
            return $this->fail('ad_err_pick', 400);
        }
        if ($crop !== '' && $mandi !== '') {
            $question = $this->clip($crop . ' ' . $mandi . '. ' . $question, self::MAX_QUESTION);
        }
        session_write_close();
        if (!$this->limiter()->hit('advisory', $this->userId, self::LIMIT_CALLS, self::LIMIT_WINDOW)) {
            return $this->fail('ad_err_limit', 429);
        }
        $res = $this->service()->getVoiceAdvisory($question, $lang);
        if (!$res['ok'] || !is_string($res['body']) || strlen($res['body']) > self::MAX_AUDIO_BYTES) {
            if (!$res['ok'] && $res['status'] === 400) {
                return $this->fail('ad_voice_needs_names', 400);
            }
            return $this->serviceFailure($res + ['status' => 0]);
        }
        http_response_code(200);
        header('Content-Type: audio/mpeg');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo $res['body'];
    }

    // --- helpers ---

    /** [question, language] from the POST, or null after sending an error. */
    private function readQuestion(): ?array {
        $q = $_POST['question'] ?? null;
        if (!is_string($q)) {
            $this->fail('ad_err_empty', 400);
            return null;
        }
        $q = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $q) ?? '');
        if ($q === '') {
            $this->fail('ad_err_empty', 400);
            return null;
        }
        if (Validator::length($q) > self::MAX_QUESTION) {
            $this->fail('ad_err_long', 400, ['n' => self::MAX_QUESTION]);
            return null;
        }
        $lang = Validator::oneOf($_POST['language'] ?? Translator::getCurrentLang(), self::LANGS) ?? 'en';
        return [$q, $lang];
    }

    private function serviceFailure(array $res) {
        $status = (int) ($res['status'] ?? 0);
        if ($status === 429) {
            return $this->fail('ad_err_limit', 429);
        }
        return $this->fail('ad_err_service', 502);
    }

    private function clip(string $s, int $max): string {
        return function_exists('mb_substr') ? mb_substr($s, 0, $max, 'UTF-8') : substr($s, 0, $max);
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
