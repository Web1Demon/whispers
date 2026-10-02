<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Core\Database;
use App\Domain\Entity\Comment;
use App\Domain\Repository\CommentRepositoryInterface;

class CommentRepository implements CommentRepositoryInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?Comment
    {
        $sql = "SELECT * FROM comments WHERE id = :id LIMIT 1";
        $row = $this->db->queryOne($sql, ['id' => $id]);
        return $row ? Comment::fromArray($row) : null;
    }

    public function create(Comment $comment): Comment
    {
        $depth = 0;
        $path = '';

        if ($comment->getParentId() !== null) {
            $parent = $this->findById($comment->getParentId());
            if ($parent !== null) {
                $depth = $parent->getDepth() + 1;
                $path = $parent->getPath();
            }
        }

        $sql = "INSERT INTO comments (
            post_id, parent_id, depth, path, content, author_pseudonym, author_avatar_from, author_avatar_to,
            fingerprint_hash, ownership_hash, likes_count, replies_count, is_deleted, created_at, updated_at
        ) VALUES (
            :post_id, :parent_id, :depth, :path, :content, :author_pseudonym, :author_avatar_from, :author_avatar_to,
            :fingerprint_hash, :ownership_hash, :likes_count, :replies_count, :is_deleted, :created_at, :updated_at
        )";

        $this->db->execute($sql, [
            'post_id' => $comment->getPostId(),
            'parent_id' => $comment->getParentId(),
            'depth' => $depth,
            'path' => $path,
            'content' => $comment->getContent(),
            'author_pseudonym' => $comment->getAuthorPseudonym(),
            'author_avatar_from' => $comment->getAuthorAvatarFrom(),
            'author_avatar_to' => $comment->getAuthorAvatarTo(),
            'fingerprint_hash' => $comment->getFingerprintHash(),
            'ownership_hash' => $comment->getOwnershipHash(),
            'likes_count' => $comment->getLikesCount(),
            'replies_count' => $comment->getRepliesCount(),
            'is_deleted' => $comment->isDeleted() ? 1 : 0,
            'created_at' => $comment->getCreatedAt(),
            'updated_at' => $comment->getUpdatedAt(),
        ]);

        $id = (int)$this->db->lastInsertId();

        // Update path with current id
        $newPath = ($path === '' ? '' : $path . '/') . $id;
        $this->db->execute("UPDATE comments SET path = :path WHERE id = :id", [
            'path' => $newPath,
            'id' => $id,
        ]);

        // If parent exists, increment replies_count on parent
        if ($comment->getParentId() !== null) {
            $this->updateCounters($comment->getParentId(), 0, 1);
        }

        return $this->findById($id);
    }

    public function update(Comment $comment): bool
    {
        $sql = "UPDATE comments SET content = :content, updated_at = :updated_at WHERE id = :id";
        $count = $this->db->execute($sql, [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $count > 0;
    }

    public function softDelete(int $id): bool
    {
        $sql = "UPDATE comments SET is_deleted = 1, updated_at = :updated_at WHERE id = :id";
        $count = $this->db->execute($sql, [
            'id' => $id,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $count > 0;
    }

    public function getFlatByPostId(int $postId): array
    {
        $sql = "SELECT c.*, p.author_pseudonym as parent_author_pseudonym 
                FROM comments c 
                LEFT JOIN comments p ON c.parent_id = p.id 
                WHERE c.post_id = :post_id 
                ORDER BY c.created_at ASC";

        $rows = $this->db->query($sql, ['post_id' => $postId]);
        $comments = [];

        foreach ($rows as $row) {
            $comment = Comment::fromArray($row);
            if (!empty($row['parent_author_pseudonym'])) {
                $comment->setParentAuthorPseudonym($row['parent_author_pseudonym']);
            }
            $comments[] = $comment;
        }

        return $comments;
    }

    /**
     * Reconstructs nested tree hierarchy in O(N) linear time with zero N+1 queries
     *
     * @param int $postId
     * @return Comment[]
     */
    public function getTreeByPostId(int $postId): array
    {
        $flat = $this->getFlatByPostId($postId);
        if (empty($flat)) {
            return [];
        }

        /** @var array<int, Comment> $map */
        $map = [];
        $rootComments = [];

        foreach ($flat as $comment) {
            $map[$comment->getId()] = $comment;
        }

        foreach ($flat as $comment) {
            $parentId = $comment->getParentId();
            if ($parentId !== null && isset($map[$parentId])) {
                $map[$parentId]->addChild($comment);
            } else {
                $rootComments[] = $comment;
            }
        }

        return $rootComments;
    }

    public function updateCounters(int $id, int $likesDelta, int $repliesDelta): void
    {
        $driver = $this->db->getDriver();
        $maxFn = $driver === 'mysql' ? 'GREATEST' : 'MAX';

        $sql = "UPDATE comments SET 
            likes_count = {$maxFn}(0, CAST(likes_count AS SIGNED) + :likes_delta),
            replies_count = {$maxFn}(0, CAST(replies_count AS SIGNED) + :replies_delta)
        WHERE id = :id";

        $this->db->execute($sql, [
            'id' => $id,
            'likes_delta' => $likesDelta,
            'replies_delta' => $repliesDelta,
        ]);
    }

    public function countByPostId(int $postId): int
    {
        $sql = "SELECT COUNT(*) as total FROM comments WHERE post_id = :post_id AND is_deleted = 0";
        $row = $this->db->queryOne($sql, ['post_id' => $postId]);
        return (int)($row['total'] ?? 0);
    }
}
