<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Core\Database;
use App\Domain\Entity\Like;
use App\Domain\Repository\LikeRepositoryInterface;
use App\Domain\ValueObject\TargetType;

class LikeRepository implements LikeRepositoryInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function exists(TargetType $targetType, int $targetId, string $userFingerprint): bool
    {
        $sql = "SELECT id FROM likes WHERE target_type = :target_type AND target_id = :target_id AND user_fingerprint = :fingerprint LIMIT 1";
        $row = $this->db->queryOne($sql, [
            'target_type' => $targetType->getValue(),
            'target_id' => $targetId,
            'fingerprint' => $userFingerprint,
        ]);
        return $row !== null;
    }

    public function create(Like $like): bool
    {
        $driver = $this->db->getDriver();
        $ignore = $driver === 'mysql' ? 'INSERT IGNORE INTO' : 'INSERT OR IGNORE INTO';

        $sql = "{$ignore} likes (
            target_type, target_id, user_fingerprint, created_at
        ) VALUES (
            :target_type, :target_id, :user_fingerprint, :created_at
        )";

        $count = $this->db->execute($sql, [
            'target_type' => $like->getTargetType(),
            'target_id' => $like->getTargetId(),
            'user_fingerprint' => $like->getUserFingerprint(),
            'created_at' => $like->getCreatedAt(),
        ]);

        return $count > 0;
    }

    public function delete(TargetType $targetType, int $targetId, string $userFingerprint): bool
    {
        $sql = "DELETE FROM likes WHERE target_type = :target_type AND target_id = :target_id AND user_fingerprint = :fingerprint";
        $count = $this->db->execute($sql, [
            'target_type' => $targetType->getValue(),
            'target_id' => $targetId,
            'fingerprint' => $userFingerprint,
        ]);
        return $count > 0;
    }

    public function countLikes(TargetType $targetType, int $targetId): int
    {
        $sql = "SELECT COUNT(*) as total FROM likes WHERE target_type = :target_type AND target_id = :target_id";
        $row = $this->db->queryOne($sql, [
            'target_type' => $targetType->getValue(),
            'target_id' => $targetId,
        ]);
        return (int)($row['total'] ?? 0);
    }

    public function getUserLikedIds(TargetType $targetType, array $targetIds, string $userFingerprint): array
    {
        if (empty($targetIds) || empty($userFingerprint)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($targetIds), '?'));
        $sql = "SELECT target_id FROM likes WHERE target_type = ? AND user_fingerprint = ? AND target_id IN ({$placeholders})";

        $params = array_merge([$targetType->getValue(), $userFingerprint], $targetIds);
        $rows = $this->db->query($sql, $params);

        $likedMap = [];
        foreach ($rows as $row) {
            $likedMap[(int)$row['target_id']] = true;
        }

        return $likedMap;
    }
}
