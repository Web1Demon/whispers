<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Cache\CacheInterface;
use App\Core\Database;
use App\Core\Exception\NotFoundException;
use App\Domain\Entity\Identity;
use App\Domain\Entity\Like;
use App\Domain\Repository\CommentRepositoryInterface;
use App\Domain\Repository\LikeRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\ValueObject\TargetType;
use App\Infrastructure\Security\RateLimiter;

class LikeService
{
    private LikeRepositoryInterface $likeRepo;
    private PostRepositoryInterface $postRepo;
    private CommentRepositoryInterface $commentRepo;
    private RateLimiter $rateLimiter;
    private CacheInterface $cache;
    private Database $db;

    public function __construct(
        LikeRepositoryInterface $likeRepo,
        PostRepositoryInterface $postRepo,
        CommentRepositoryInterface $commentRepo,
        RateLimiter $rateLimiter,
        CacheInterface $cache,
        Database $db
    ) {
        $this->likeRepo = $likeRepo;
        $this->postRepo = $postRepo;
        $this->commentRepo = $commentRepo;
        $this->rateLimiter = $rateLimiter;
        $this->cache = $cache;
        $this->db = $db;
    }

    public function toggleLike(string $targetTypeValue, int $targetId, Identity $identity): array
    {
        $targetType = new TargetType($targetTypeValue);
        $fingerprint = $identity->getFingerprint();

        // 1. Rate limiting
        $this->rateLimiter->check('likes', $fingerprint);

        // 2. Validate target exists
        if ($targetType->isPost()) {
            $post = $this->postRepo->findById($targetId);
            if ($post === null || $post->isDeleted()) {
                throw new NotFoundException("Post not found.");
            }
        } else {
            $comment = $this->commentRepo->findById($targetId);
            if ($comment === null || $comment->isDeleted()) {
                throw new NotFoundException("Comment not found.");
            }
        }

        // 3. Atomically toggle like inside database transaction
        return $this->db->transaction(function () use ($targetType, $targetId, $fingerprint) {
            $alreadyLiked = $this->likeRepo->exists($targetType, $targetId, $fingerprint);

            if ($alreadyLiked) {
                // Unlike
                $this->likeRepo->delete($targetType, $targetId, $fingerprint);
                $delta = -1;
                $isLiked = false;
            } else {
                // Like
                $like = new Like(null, $targetType->getValue(), $targetId, $fingerprint);
                $this->likeRepo->create($like);
                $delta = 1;
                $isLiked = true;
            }

            // Sync denormalized counters
            if ($targetType->isPost()) {
                $this->postRepo->updateCounters($targetId, $delta, 0);
                $updated = $this->postRepo->findById($targetId);
                $newCount = $updated ? $updated->getLikesCount() : 0;
            } else {
                $this->commentRepo->updateCounters($targetId, $delta, 0);
                $updated = $this->commentRepo->findById($targetId);
                $newCount = $updated ? $updated->getLikesCount() : 0;
            }

            $this->cache->clear();

            return [
                'target_type' => $targetType->getValue(),
                'target_id' => $targetId,
                'is_liked' => $isLiked,
                'likes_count' => $newCount,
                'message' => $isLiked ? 'Liked successfully' : 'Unliked successfully',
            ];
        });
    }
}
