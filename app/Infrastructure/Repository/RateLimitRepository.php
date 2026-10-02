<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Core\Database;
use App\Domain\Repository\RateLimitRepositoryInterface;

class RateLimitRepository implements RateLimitRepositoryInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function recordHit(string $key, int $decaySeconds): int
    {
        $now = time();
        $keyHash = hash('sha256', $key);

        // 1. Insert current hit
        $sql = "INSERT INTO rate_limits (key_hash, hit_time) VALUES (:key_hash, :hit_time)";
        $this->db->execute($sql, [
            'key_hash' => $keyHash,
            'hit_time' => $now,
        ]);

        // 2. Count active hits within window
        $windowStart = $now - $decaySeconds;
        $countSql = "SELECT COUNT(*) as total FROM rate_limits WHERE key_hash = :key_hash AND hit_time >= :window_start";
        $row = $this->db->queryOne($countSql, [
            'key_hash' => $keyHash,
            'window_start' => $windowStart,
        ]);

        // 3. Probabilistic cleanup of stale records (1 in 20 requests)
        if (random_int(1, 20) === 1) {
            $cleanupSql = "DELETE FROM rate_limits WHERE hit_time < :cleanup_time";
            $this->db->execute($cleanupSql, ['cleanup_time' => $now - 3600]);
        }

        return (int)($row['total'] ?? 1);
    }

    public function getAttempts(string $key, int $decaySeconds): int
    {
        $now = time();
        $keyHash = hash('sha256', $key);
        $windowStart = $now - $decaySeconds;

        $countSql = "SELECT COUNT(*) as total FROM rate_limits WHERE key_hash = :key_hash AND hit_time >= :window_start";
        $row = $this->db->queryOne($countSql, [
            'key_hash' => $keyHash,
            'window_start' => $windowStart,
        ]);

        return (int)($row['total'] ?? 0);
    }

    public function reset(string $key): void
    {
        $keyHash = hash('sha256', $key);
        $this->db->execute("DELETE FROM rate_limits WHERE key_hash = :key_hash", ['key_hash' => $keyHash]);
    }
}
