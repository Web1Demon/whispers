<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Core\Database;
use App\Domain\Entity\Post;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\ValueObject\FeedSort;
use PDO;

class PostRepository implements PostRepositoryInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function findById(int $id): ?Post
    {
        $sql = "SELECT * FROM posts WHERE id = :id LIMIT 1";
        $row = $this->db->queryOne($sql, ['id' => $id]);
        return $row ? Post::fromArray($row) : null;
    }

    public function findByUid(string $uid): ?Post
    {
        $sql = "SELECT * FROM posts WHERE uid = :uid LIMIT 1";
        $row = $this->db->queryOne($sql, ['uid' => $uid]);
        return $row ? Post::fromArray($row) : null;
    }

    public function create(Post $post): Post
    {
        $sql = "INSERT INTO posts (
            uid, title, content, category, author_pseudonym, author_avatar_from, author_avatar_to,
            fingerprint_hash, ownership_hash, likes_count, comments_count, views_count, hot_score,
            is_pinned, is_deleted, created_at, updated_at
        ) VALUES (
            :uid, :title, :content, :category, :author_pseudonym, :author_avatar_from, :author_avatar_to,
            :fingerprint_hash, :ownership_hash, :likes_count, :comments_count, :views_count, :hot_score,
            :is_pinned, :is_deleted, :created_at, :updated_at
        )";

        $this->db->execute($sql, [
            'uid' => $post->getUid(),
            'title' => $post->getTitle(),
            'content' => $post->getContent(),
            'category' => $post->getCategory(),
            'author_pseudonym' => $post->getAuthorPseudonym(),
            'author_avatar_from' => $post->getAuthorAvatarFrom(),
            'author_avatar_to' => $post->getAuthorAvatarTo(),
            'fingerprint_hash' => $post->getFingerprintHash(),
            'ownership_hash' => $post->getOwnershipHash(),
            'likes_count' => $post->getLikesCount(),
            'comments_count' => $post->getCommentsCount(),
            'views_count' => $post->getViewsCount(),
            'hot_score' => $post->getHotScore(),
            'is_pinned' => $post->isPinned() ? 1 : 0,
            'is_deleted' => $post->isDeleted() ? 1 : 0,
            'created_at' => $post->getCreatedAt(),
            'updated_at' => $post->getUpdatedAt(),
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->findById($id);
    }

    public function update(Post $post): bool
    {
        $sql = "UPDATE posts SET
            title = :title,
            content = :content,
            category = :category,
            updated_at = :updated_at
        WHERE id = :id";

        $count = $this->db->execute($sql, [
            'id' => $post->getId(),
            'title' => $post->getTitle(),
            'content' => $post->getContent(),
            'category' => $post->getCategory(),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $count > 0;
    }

    public function softDelete(int $id): bool
    {
        $sql = "UPDATE posts SET is_deleted = 1, updated_at = :updated_at WHERE id = :id";
        $count = $this->db->execute($sql, [
            'id' => $id,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $count > 0;
    }

    public function getFeed(
        int $page = 1,
        int $limit = 15,
        ?FeedSort $sort = null,
        ?string $category = null,
        ?string $search = null
    ): array {
        $sort = $sort ?? new FeedSort(FeedSort::RECENT);
        $offset = max(0, ($page - 1) * $limit);

        $conditions = ["is_deleted = 0"];
        $params = [];

        if ($category !== null && $category !== '' && $category !== 'all') {
            $conditions[] = "category = :category";
            $params['category'] = strtolower($category);
        }

        if ($search !== null && trim($search) !== '') {
            $conditions[] = "(title LIKE :search OR content LIKE :search OR author_pseudonym LIKE :search)";
            $params['search'] = '%' . trim($search) . '%';
        }

        $whereClause = implode(' AND ', $conditions);
        $orderClause = $sort->getOrderBySql();

        $sql = "SELECT * FROM posts WHERE {$whereClause} ORDER BY {$orderClause} LIMIT :limit OFFSET :offset";

        $stmt = $this->db->getConnection()->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue(":{$key}", $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        $posts = [];
        foreach ($rows as $row) {
            $posts[] = Post::fromArray($row);
        }

        return $posts;
    }

    public function countFeed(?string $category = null, ?string $search = null): int
    {
        $conditions = ["is_deleted = 0"];
        $params = [];

        if ($category !== null && $category !== '' && $category !== 'all') {
            $conditions[] = "category = :category";
            $params['category'] = strtolower($category);
        }

        if ($search !== null && trim($search) !== '') {
            $conditions[] = "(title LIKE :search OR content LIKE :search OR author_pseudonym LIKE :search)";
            $params['search'] = '%' . trim($search) . '%';
        }

        $whereClause = implode(' AND ', $conditions);
        $sql = "SELECT COUNT(*) as total FROM posts WHERE {$whereClause}";
        $row = $this->db->queryOne($sql, $params);
        return (int)($row['total'] ?? 0);
    }

    public function incrementViews(int $id): void
    {
        $sql = "UPDATE posts SET views_count = views_count + 1 WHERE id = :id";
        $this->db->execute($sql, ['id' => $id]);
    }

    public function updateCounters(int $id, int $likesDelta, int $commentsDelta): void
    {
        // GREATEST/MAX compatibility
        $driver = $this->db->getDriver();
        $maxFn = $driver === 'mysql' ? 'GREATEST' : 'MAX';

        $sql = "UPDATE posts SET 
            likes_count = {$maxFn}(0, CAST(likes_count AS SIGNED) + :likes_delta),
            comments_count = {$maxFn}(0, CAST(comments_count AS SIGNED) + :comments_delta)
        WHERE id = :id";

        $this->db->execute($sql, [
            'id' => $id,
            'likes_delta' => $likesDelta,
            'comments_delta' => $commentsDelta,
        ]);
    }

    public function recalculateHotScores(): void
    {
        $driver = $this->db->getDriver();
        if ($driver === 'mysql') {
            $sql = "UPDATE posts SET hot_score = (
                (likes_count + (comments_count * 2.0)) /
                (GREATEST(0.1, (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(created_at)) / 3600.0) + 2.0)
            ) WHERE is_deleted = 0";
        } else {
            $sql = "UPDATE posts SET hot_score = (
                (likes_count + (comments_count * 2.0)) /
                (MAX(0.1, (strftime('%s', 'now') - strftime('%s', created_at)) / 3600.0) + 2.0)
            ) WHERE is_deleted = 0";
        }

        $this->db->execute($sql);
    }

    public function getCategories(): array
    {
        $sql = "SELECT category, COUNT(*) as count FROM posts WHERE is_deleted = 0 GROUP BY category ORDER BY count DESC";
        return $this->db->query($sql);
    }

    public function getTotalStats(): array
    {
        $sql = "SELECT 
            COUNT(*) as total_posts,
            COALESCE(SUM(likes_count), 0) as total_likes,
            COALESCE(SUM(comments_count), 0) as total_comments,
            COALESCE(SUM(views_count), 0) as total_views
        FROM posts WHERE is_deleted = 0";

        return $this->db->queryOne($sql) ?: [
            'total_posts' => 0,
            'total_likes' => 0,
            'total_comments' => 0,
            'total_views' => 0,
        ];
    }
}
