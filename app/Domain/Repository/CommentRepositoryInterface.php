<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Comment;

interface CommentRepositoryInterface
{
    public function findById(int $id): ?Comment;
    public function create(Comment $comment): Comment;
    public function update(Comment $comment): bool;
    public function softDelete(int $id): bool;
    public function getTreeByPostId(int $postId): array;
    public function getFlatByPostId(int $postId): array;
    public function updateCounters(int $id, int $likesDelta, int $repliesDelta): void;
    public function countByPostId(int $postId): int;
}
