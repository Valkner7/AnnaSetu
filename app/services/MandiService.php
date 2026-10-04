<?php
namespace App\Services;

class MandiService {
    private $base;
    private $timeout;
    private $connectTimeout;

    public function __construct() {
        $cfg = require __DIR__ . '/../../config/mandi.php';
        $this->base = rtrim($cfg['api_base'], '/');
        $this->timeout = $cfg['timeout_seconds'] ?? 90;
        $this->connectTimeout = $cfg['connect_timeout_seconds'] ?? 20;
    }

    public function getMeta($crop = null) {
        return $this->get('/meta', $crop ? ['crop' => $crop] : []);
    }

    public function getPredict($crop, $mandi) {
        return $this->get('/predict', ['crop' => $crop, 'mandi' => $mandi]);
    }

    private function get($path, $params = []) {
        $url = $this->base . $path;
        if ($params) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
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
