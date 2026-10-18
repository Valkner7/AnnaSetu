<?php
namespace App\Helpers;

/**
 * Small per-user rate limit kept in files (no database needed). Used for the
 * advisory page: the price service limits by IP address, and every AnnaSetu
 * user reaches it from the same server address, so without this one busy user
 * could use up everybody's allowance, and one user could spend the whole
 * Gemini quota.
 */
class RateLimit {
    private $dir;

    public function __construct(?string $dir = null) {
        $this->dir = rtrim($dir ?? (__DIR__ . '/../../storage/cache/ratelimit'), '/\\');
    }

    /**
     * Count one use if the user is under the limit.
     * @return bool true = allowed (and counted), false = over the limit
     */
    public function hit(string $bucket, int $userId, int $max, int $windowSeconds, ?int $now = null): bool {
        $now = $now ?? time();
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0755, true) && !is_dir($this->dir)) {
            error_log('RateLimit: cannot create ' . $this->dir);
            return true;                       // never lock people out because of a disk problem
        }
        $file = $this->dir . '/' . preg_replace('/[^a-z0-9_-]/i', '', $bucket) . '-' . $userId . '.json';
        $fh = @fopen($file, 'c+');
        if (!$fh) {
            return true;
        }
        flock($fh, LOCK_EX);
        $raw = stream_get_contents($fh);
        $times = json_decode($raw === false ? '' : $raw, true);
        $times = is_array($times) ? array_values(array_filter($times, function ($t) use ($now, $windowSeconds) {
            return is_int($t) && $t > $now - $windowSeconds;
        })) : [];
        $allowed = count($times) < $max;
        if ($allowed) {
            $times[] = $now;
        }
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode($times));
        flock($fh, LOCK_UN);
        fclose($fh);
        return $allowed;
    }
}
