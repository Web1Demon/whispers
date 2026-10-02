<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Post;
use App\Domain\ValueObject\FeedSort;

interface PostRepositoryInterface
{
    public function findById(int $id): ?Post;
    public function findByUid(string $uid): ?Post;
    public function create(Post $post): Post;
    public function update(Post $post): bool;
    public function softDelete(int $id): bool;
    public function getFeed(int $page = 1, int $limit = 15, ?FeedSort $sort = null, ?string $category = null, ?string $search = null): array;
    public function countFeed(?string $category = null, ?string $search = null): int;
    public function incrementViews(int $id): void;
    public function updateCounters(int $id, int $likesDelta, int $commentsDelta): void;
    public function recalculateHotScores(): void;
    public function getCategories(): array;
    public function getTotalStats(): array;
}
