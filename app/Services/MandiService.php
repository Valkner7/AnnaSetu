<?php
namespace App\Services;

class MandiService {
    private $base;
    private $fallback;
    private $localConnectTimeout;
    private $lastBase;
    private $timeout;
    private $connectTimeout;
    private $cacheDir;
    private $cacheTtl;
    private $apiSecret;

    /**
     * @param array|null $override Optional config overrides (used by tests).
     */
    public function __construct(?array $override = null) {
        $cfg = require __DIR__ . '/../../config/mandi.php';
        if ($override) {
            $cfg = array_merge($cfg, $override);
        }
        $this->base = rtrim($cfg['api_base'], '/');
        $this->fallback = rtrim((string) ($cfg['fallback_base'] ?? ''), '/');
        $this->localConnectTimeout = (int) ($cfg['local_connect_timeout_seconds'] ?? 2);
        $this->lastBase = $this->base;
        $this->timeout = $cfg['timeout_seconds'] ?? 90;
        $this->connectTimeout = $cfg['connect_timeout_seconds'] ?? 20;
        $this->cacheTtl = (int) ($cfg['cache_ttl_seconds'] ?? 14400);
        $this->apiSecret = is_string($cfg['api_secret'] ?? null) ? $cfg['api_secret'] : '';
        $this->cacheDir = rtrim($cfg['cache_dir'] ?? (__DIR__ . '/../../storage/cache/mandi'), '/\\');
    }

    // Endpoints that spend Gemini / text-to-speech quota. The shared secret (M10)
    // is sent to these and to nothing else.
    public const SECRET_PATHS = ['/advisory', '/compare-advisory', '/voice-advisory'];

    // Used only when the price service has never been reachable (no cache yet).
    // The service's own list (/meta -> reliable_crops) is the source of truth.
    public const FALLBACK_CROPS = ['Potato', 'Onion', 'Tomato'];

    /**
     * Crops the price service can forecast (M9).
     *
     * @param bool $allowNetwork false = read the cache only and never wait on
     *        the (possibly sleeping) service, so page renders stay fast.
     * @return string[] never empty: falls back to FALLBACK_CROPS.
     */
    public function getReliableCrops(bool $allowNetwork = true): array {
        if ($allowNetwork) {
            $res = $this->get('/meta');
            $data = $res['ok'] ? $res['data'] : null;
        } else {
            $rec = $this->cacheRead($this->cacheKey('/meta', []));
            $data = $rec['data'] ?? null;     // any age: this list changes very rarely
        }
        $list = is_array($data) ? self::cleanCropList($data['reliable_crops'] ?? null) : [];
        return $list ?: self::FALLBACK_CROPS;
    }

    /**
     * Accept only a short list of plain, non-empty strings. Anything else
     * (wrong type, empty, huge, odd characters) is dropped so a bad response
     * can never widen what the app is willing to send upstream.
     */
    public static function cleanCropList($raw): array {
        if (!is_array($raw) || count($raw) > 50) {
            return [];
        }
        $out = [];
        foreach ($raw as $name) {
            if (!is_string($name)) {
                continue;
            }
            $name = trim($name);
            if ($name === '' || strlen($name) > 60 || preg_match('/[\x00-\x1F\x7F<>"\'&]/', $name)) {
                continue;
            }
            $out[$name] = true;      // de-duplicate, keep order
        }
        return array_map('strval', array_keys($out));
    }

    public function getMeta($crop = null) {
        return $this->get('/meta', $crop ? ['crop' => $crop] : []);
    }

    public function getPredict($crop, $mandi) {
        return $this->get('/predict', ['crop' => $crop, 'mandi' => $mandi]);
    }

    /** Recent daily prices for one crop and mandi, for the price chart (F1). 7 to 365 days. */
    public function getHistory($crop, $mandi, $days = 60) {
        $days = max(7, min(365, (int) $days));
        return $this->get('/history', ['crop' => $crop, 'mandi' => $mandi, 'days' => $days]);
    }

    /**
     * Same crop at 2 to 5 mandis, ranked by the service (F5). Names are sorted
     * so the same set of mandis always hits the same cache file.
     */
    public function getCompare($crop, array $mandis) {
        $mandis = array_values($mandis);
        sort($mandis, SORT_NATURAL | SORT_FLAG_CASE);
        return $this->get('/compare', ['crop' => $crop, 'mandis' => implode(',', $mandis)]);
    }

    /**
     * Mandis nearest to a point, with each one's latest price for $crop (F4).
     * Not cached: every location is different, so caching would only fill
     * the cache folder. The caller rounds the coordinates first.
     */
    public function getNearby($lat, $lon, $crop, $limit = 10) {
        return $this->fetchRemote('/api/nearby-mandis', [
            'lat' => $lat, 'lon' => $lon, 'crop' => $crop, 'limit' => max(1, min(50, (int) $limit)),
        ]);
    }

    // --- Advisory (F6). Never cached: every question is different, and each call may cost quota. ---

    public function getAdvisory($crop, $mandi, $question, $language) {
        return $this->fetchRemote('/advisory', ['crop' => $crop, 'mandi' => $mandi, 'question' => $question, 'language' => $language]);
    }

    public function getCompareAdvisory($crop, array $mandis, $question, $language) {
        return $this->fetchRemote('/compare-advisory', ['crop' => $crop, 'mandis' => implode(',', array_values($mandis)), 'question' => $question, 'language' => $language]);
    }

    /**
     * Spoken advisory (MP3). The service finds the crop and mandi in the
     * question text itself. Result keys: ok, status, error, body, content_type, via.
     */
    public function getVoiceAdvisory($question, $language) {
        $params = ['question' => $question, 'language' => $language];
        $path = '/voice-advisory';
        $res = $this->httpPostBinary($this->base, $path, $params);
        $this->lastBase = $this->base;
        $down = ($res['status'] === 0 || $res['status'] >= 500);
        if ($down && $this->fallback !== '' && strcasecmp($this->fallback, $this->base) !== 0) {
            $res2 = $this->httpPostBinary($this->fallback, $path, $params);
            if ($res2['ok'] || $res2['status'] !== 0) {
                $this->lastBase = $this->fallback;
                $res = $res2;
            }
        }
        $res['via'] = self::isLoopback($this->lastBase) ? 'local' : 'hosted';
        return $res;
    }

    /**
     * Live health of the price service (not cached: a stale "ok" would be
     * worse than none). Result keys as for fetchRemote(), plus 'via'.
     */
    public function getStatus() {
        return $this->fetchRemote('/status');
    }

    /** 'local' or 'hosted': which address answered the most recent live call. */
    public function lastSource(): string {
        return self::isLoopback($this->lastBase) ? 'local' : 'hosted';
    }

    public static function isLoopback($base): bool {
        $host = strtolower(trim((string) parse_url((string) $base, PHP_URL_HOST), '[]'));
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * GET with a small file cache (M4).
     *
     * - A fresh cached copy (younger than cache_ttl_seconds) is returned
     *   without calling the price service, so dropdown changes do not wake
     *   the free-tier host.
     * - Only successful responses are cached. The key includes the path and
     *   every parameter, so Potato/Rayya can never be served for Onion/Khanna.
     * - If the live call fails, the last cached copy is served marked
     *   'stale' => true, however old it is. With no copy, the error is returned.
     *
     * Result keys: ok, status, error, data, cached (bool), stale (bool).
     */
    private function get($path, $params = []) {
        $key = $this->cacheKey($path, $params);
        $cached = $this->cacheRead($key);

        if ($cached !== null && (time() - $cached['saved_at']) < $this->cacheTtl) {
            return ['ok' => true, 'status' => 200, 'error' => null, 'data' => $cached['data'],
                    'cached' => true, 'stale' => false];
        }

        $res = $this->fetchRemote($path, $params);
        $res['cached'] = false;
        $res['stale'] = false;

        if ($res['ok']) {
            $this->cacheWrite($key, $res['data']);
            return $res;
        }

        // 404/422 mean "no forecast for this pair": a real answer, not an outage.
        $isOutage = ($res['status'] === 0 || $res['status'] >= 500);
        if ($cached !== null && $isOutage) {
            return ['ok' => true, 'status' => 200, 'error' => null, 'data' => $cached['data'],
                    'cached' => true, 'stale' => true];
        }
        return $res;
    }

    private function cacheKey($path, $params) {
        ksort($params);
        return hash('sha256', $path . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986));
    }

    private function cacheRead($key) {
        $file = $this->cacheDir . '/' . $key . '.json';
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        $rec = $raw === false ? null : json_decode($raw, true);
        if (!is_array($rec) || !isset($rec['saved_at']) || !array_key_exists('data', $rec) || !is_array($rec['data'])) {
            return null;
        }
        return $rec;
    }

    private function cacheWrite($key, $data) {
        if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0755, true) && !is_dir($this->cacheDir)) {
            error_log('MandiService: cannot create cache dir ' . $this->cacheDir);
            return;
        }
        $file = $this->cacheDir . '/' . $key . '.json';
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $json = json_encode(['saved_at' => time(), 'data' => $data], JSON_UNESCAPED_UNICODE);
        // Write then rename, so a reader never sees a half-written file.
        if ($json === false || @file_put_contents($tmp, $json) === false || !@rename($tmp, $file)) {
            @unlink($tmp);
            error_log('MandiService: cannot write cache file ' . $file);
        }
    }

    /** Headers for one upstream request. The secret goes only to SECRET_PATHS. */
    protected function requestHeaders($path) {
        $headers = ['Accept: application/json'];
        if ($this->apiSecret !== '' && in_array($path, self::SECRET_PATHS, true)) {
            $headers[] = 'X-Api-Secret: ' . $this->apiSecret;
        }
        return $headers;
    }

    /**
     * One live call. Tries the main address; if nothing answered at all (status 0)
     * or the service is down (5xx), tries the fallback address once, so the
     * app still works when the bundled service is not running.
     */
    protected function fetchRemote($path, $params = []) {
        $res = $this->httpGet($this->base, $path, $params);
        $this->lastBase = $this->base;
        $down = ($res['status'] === 0 || $res['status'] >= 500);
        if ($down && $this->fallback !== '' && strcasecmp($this->fallback, $this->base) !== 0) {
            $res2 = $this->httpGet($this->fallback, $path, $params);
            if ($res2['ok'] || $res2['status'] !== 0) {
                $this->lastBase = $this->fallback;
                $res = $res2;
            }
        }
        $res['via'] = self::isLoopback($this->lastBase) ? 'local' : 'hosted';
        return $res;
    }

    /** A POST (parameters in the query string, as the service expects) that returns raw bytes. */
    protected function httpPostBinary($base, $path, $params = []) {
        $url = $base . $path . ($params ? '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986) : '');
        $connect = self::isLoopback($base) ? min($this->connectTimeout, $this->localConnectTimeout) : $this->connectTimeout;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_HTTPHEADER => $this->requestHeaders($path),
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'status' => 0, 'error' => 'Could not reach the price service (' . $err . ')', 'body' => null, 'content_type' => ''];
        }
        if ($status >= 200 && $status < 300 && is_string($body) && $body !== '') {
            return ['ok' => true, 'status' => $status, 'error' => null, 'body' => $body, 'content_type' => $type];
        }
        $data = json_decode((string) $body, true);
        $detail = (is_array($data) && is_string($data['detail'] ?? null)) ? $data['detail'] : 'Unexpected response from the price service';
        return ['ok' => false, 'status' => $status, 'error' => $detail, 'body' => null, 'content_type' => ''];
    }

    /** A GET to one specific base address. No fallback logic here. */
    protected function httpGet($base, $path, $params = []) {
        $url = $base . $path;
        if ($params) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        $connect = self::isLoopback($base) ? min($this->connectTimeout, $this->localConnectTimeout) : $this->connectTimeout;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $connect,
            CURLOPT_HTTPHEADER => $this->requestHeaders($path),
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'status' => 0, 'error' => 'Could not reach the price service (' . $err . ')', 'data' => null];
        }
        $data = json_decode($body, true);
        if ($status >= 200 && $status < 300 && is_array($data)) {
            return ['ok' => true, 'status' => $status, 'error' => null, 'data' => $data];
        }
        $detail = 'Unexpected response from the price service';
        if (is_array($data) && isset($data['detail'])) {
            $detail = is_string($data['detail']) ? $data['detail'] : json_encode($data['detail']);
        }
        return ['ok' => false, 'status' => $status, 'error' => $detail, 'data' => null];
    }
}
