<?php
namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Price alerts (table price_alerts, database/schema_phase7.sql).
 * Every query that touches one alert is also scoped by user_id, so a user can
 * never read or change another user's alert. Timestamps come from PHP, not the
 * database, so the same SQL runs in the tests.
 */
class PriceAlert {
    private $db;

    public function __construct(?PDO $pdo = null) {
        $this->db = $pdo ?: Database::getInstance()->getConnection();
    }

    private static function now(): string { return date('Y-m-d H:i:s'); }

    public function countActive(int $userId): int {
        $st = $this->db->prepare("SELECT COUNT(*) FROM price_alerts WHERE user_id = ? AND status = 'active'");
        $st->execute([$userId]);
        return (int) $st->fetchColumn();
    }

    /** True if the same active alert already exists (same crop, mandi, direction and target). */
    public function exists(int $userId, string $crop, string $mandi, string $direction, float $target): bool {
        $st = $this->db->prepare("SELECT 1 FROM price_alerts WHERE user_id = ? AND crop = ? AND mandi = ? AND direction = ? AND target_price = ? AND status = 'active' LIMIT 1");
        $st->execute([$userId, $crop, $mandi, $direction, $target]);
        return (bool) $st->fetchColumn();
    }

    public function create(int $userId, string $crop, string $mandi, string $direction, float $target): int {
        $st = $this->db->prepare("INSERT INTO price_alerts (user_id, crop, mandi, direction, target_price, status, seen, created_at) VALUES (?, ?, ?, ?, ?, 'active', 0, ?)");
        $st->execute([$userId, $crop, $mandi, $direction, $target, self::now()]);
        return (int) $this->db->lastInsertId();
    }

    public function listByUser(int $userId): array {
        $st = $this->db->prepare("SELECT * FROM price_alerts WHERE user_id = ? ORDER BY (status = 'triggered') DESC, id DESC");
        $st->execute([$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listActive(?int $userId = null): array {
        if ($userId === null) {
            $st = $this->db->query("SELECT * FROM price_alerts WHERE status = 'active' ORDER BY id");
        } else {
            $st = $this->db->prepare("SELECT * FROM price_alerts WHERE status = 'active' AND user_id = ? ORDER BY id");
            $st->execute([$userId]);
        }
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Triggered alerts the user has not looked at yet. */
    public function countUnseen(int $userId): int {
        $st = $this->db->prepare("SELECT COUNT(*) FROM price_alerts WHERE user_id = ? AND status = 'triggered' AND seen = 0");
        $st->execute([$userId]);
        return (int) $st->fetchColumn();
    }

    public function recentTriggered(int $userId, int $limit = 3): array {
        $limit = max(1, min(20, $limit));
        $st = $this->db->prepare("SELECT * FROM price_alerts WHERE user_id = ? AND status = 'triggered' ORDER BY triggered_at DESC, id DESC LIMIT $limit");
        $st->execute([$userId]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Record the latest price seen for an alert that did not trigger. */
    public function recordCheck(int $id, float $price, string $priceDate): void {
        $st = $this->db->prepare("UPDATE price_alerts SET last_price = ?, last_price_date = ?, last_checked_at = ? WHERE id = ?");
        $st->execute([$price, $priceDate, self::now(), $id]);
    }

    /** Mark triggered. Only an alert that is still active changes, so two checkers cannot trigger it twice. */
    public function markTriggered(int $id, float $price, string $priceDate): bool {
        $now = self::now();
        $st = $this->db->prepare("UPDATE price_alerts SET status = 'triggered', triggered_price = ?, triggered_price_date = ?, triggered_at = ?, last_price = ?, last_price_date = ?, last_checked_at = ?, seen = 0 WHERE id = ? AND status = 'active'");
        $st->execute([$price, $priceDate, $now, $price, $priceDate, $now, $id]);
        return $st->rowCount() === 1;
    }

    public function delete(int $id, int $userId): bool {
        $st = $this->db->prepare("DELETE FROM price_alerts WHERE id = ? AND user_id = ?");
        $st->execute([$id, $userId]);
        return $st->rowCount() === 1;
    }

    /** Put a triggered alert back to watching. */
    public function rearm(int $id, int $userId): bool {
        $st = $this->db->prepare("UPDATE price_alerts SET status = 'active', triggered_price = NULL, triggered_price_date = NULL, triggered_at = NULL, seen = 0 WHERE id = ? AND user_id = ? AND status = 'triggered'");
        $st->execute([$id, $userId]);
        return $st->rowCount() === 1;
    }

    public function markAllSeen(int $userId): void {
        $st = $this->db->prepare("UPDATE price_alerts SET seen = 1 WHERE user_id = ? AND status = 'triggered' AND seen = 0");
        $st->execute([$userId]);
    }
}
