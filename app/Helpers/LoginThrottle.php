<?php
namespace App\Helpers;

/**
 * Simple login throttling (N5). Counts failed attempts per phone + client IP
 * in small JSON files under storage/login_attempts/ (gitignored). After
 * MAX_ATTEMPTS failures inside WINDOW seconds the pair is locked until the
 * window passes. A successful login clears the counter.
 */
class LoginThrottle {
    const MAX_ATTEMPTS = 5;
    const WINDOW = 900; // 15 minutes

    private static function file(string $phone): string {
        $dir = __DIR__ . '/../../storage/login_attempts';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $key = hash('sha256', strtolower(trim($phone)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
        return $dir . '/' . $key . '.json';
    }

    private static function read(string $phone): array {
        $f = self::file($phone);
        $d = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
        if (!is_array($d) || (time() - ($d['first'] ?? 0)) > self::WINDOW) {
            return ['count' => 0, 'first' => time()];
        }
        return $d;
    }

    /** Seconds the caller must still wait, or 0 if allowed to try. */
    public static function lockedFor(string $phone): int {
        $d = self::read($phone);
        if ($d['count'] >= self::MAX_ATTEMPTS) {
            return max(1, self::WINDOW - (time() - $d['first']));
        }
        return 0;
    }

    public static function fail(string $phone): void {
        $d = self::read($phone);
        $d['count']++;
        @file_put_contents(self::file($phone), json_encode($d), LOCK_EX);
    }

    public static function clear(string $phone): void {
        $f = self::file($phone);
        if (is_file($f)) {
            @unlink($f);
        }
    }
}
