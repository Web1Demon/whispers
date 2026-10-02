<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Like;
use App\Domain\ValueObject\TargetType;

interface LikeRepositoryInterface
{
    public function exists(TargetType $targetType, int $targetId, string $userFingerprint): bool;
    public function create(Like $like): bool;
    public function delete(TargetType $targetType, int $targetId, string $userFingerprint): bool;
    public function countLikes(TargetType $targetType, int $targetId): int;
    public function getUserLikedIds(TargetType $targetType, array $targetIds, string $userFingerprint): array;
}
