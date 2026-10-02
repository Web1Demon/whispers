<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Cache\CacheInterface;
use App\Core\Database;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\UnauthorizedException;
use App\Core\Exception\ValidationException;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Identity;
use App\Domain\Repository\CommentRepositoryInterface;
use App\Domain\Repository\LikeRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\ValueObject\TargetType;
use App\Infrastructure\Security\ContentModerator;
use App\Infrastructure\Security\IdentityManager;
use App\Infrastructure\Security\RateLimiter;

class CommentService
{
    private CommentRepositoryInterface $commentRepo;
    private PostRepositoryInterface $postRepo;
    private LikeRepositoryInterface $likeRepo;
    private IdentityManager $identityManager;
    private ContentModerator $moderator;
    private RateLimiter $rateLimiter;
    private CacheInterface $cache;
    private Database $db;

    public function __construct(
        CommentRepositoryInterface $commentRepo,
        PostRepositoryInterface $postRepo,
        LikeRepositoryInterface $likeRepo,
        IdentityManager $identityManager,
        ContentModerator $moderator,
        RateLimiter $rateLimiter,
        CacheInterface $cache,
        Database $db
    ) {
        $this->commentRepo = $commentRepo;
        $this->postRepo = $postRepo;
        $this->likeRepo = $likeRepo;
        $this->identityManager = $identityManager;
        $this->moderator = $moderator;
        $this->rateLimiter = $rateLimiter;
        $this->cache = $cache;
        $this->db = $db;
    }

    public function addComment(int $postId, ?int $parentId, string $content, Identity $identity): array
    {
        // 1. Rate limiting check
        $this->rateLimiter->check('comments', $identity->getFingerprint());

        // 2. Validate post exists
        $post = $this->postRepo->findById($postId);
        if ($post === null || $post->isDeleted()) {
            throw new NotFoundException("Whisper post not found.");
        }

        // 3. If parentId provided, validate parent exists and matches same postId
        $depth = 0;
        $parentComment = null;
        if ($parentId !== null && $parentId > 0) {
            $parentComment = $this->commentRepo->findById($parentId);
            if ($parentComment === null || $parentComment->isDeleted()) {
                throw new NotFoundException("Parent comment not found.");
            }
            if ($parentComment->getPostId() !== $postId) {
                throw new ValidationException("Parent comment does not belong to this post.");
            }
            if ($parentComment->getDepth() >= 10) {
                throw new ValidationException("Maximum nested reply depth reached.");
            }
            $depth = $parentComment->getDepth() + 1;
        }

        // 4. Content moderation
        $cleanContent = $this->moderator->validateComment($content);

        // 5. Transactional insert & counter sync
        return $this->db->transaction(function () use ($postId, $parentId, $depth, $cleanContent, $identity, $parentComment) {
            $now = date('Y-m-d H:i:s');
            $tempId = bin2hex(random_bytes(6));
            $ownershipToken = $this->identityManager->generateOwnershipToken('comment', $tempId, $identity->getFingerprint(), $now);

            $comment = new Comment(
                null,
                $postId,
                $parentId,
                $depth,
                '',
                $cleanContent,
                $identity->getPseudonym(),
                $identity->getAvatarGradientFrom(),
                $identity->getAvatarGradientTo(),
                $identity->getFingerprint(),
                $ownershipToken,
                0,
                0,
                false,
                $now,
                $now
            );

            $saved = $this->commentRepo->create($comment);

            // Increment post comments count
            $this->postRepo->updateCounters($postId, 0, 1);

            $this->cache->clear();

            $commentArray = $saved->toArray();
            if ($parentComment !== null) {
                $commentArray['parent_author'] = $parentComment->getAuthorPseudonym();
            }
            $commentArray['viewer_state']['is_owner'] = true;
            $commentArray['viewer_state']['has_liked'] = false;

            return [
                'comment' => $commentArray,
                'ownership_token' => $ownershipToken,
            ];
        });
    }

    public function getCommentsTree(int $postId, ?Identity $viewerIdentity = null): array
    {
        $flat = $this->commentRepo->getFlatByPostId($postId);
        if (empty($flat)) {
            return [];
        }

        $viewerFingerprint = $viewerIdentity?->getFingerprint();
        $likedMap = [];

        if ($viewerFingerprint !== null) {
            $commentIds = array_map(fn(Comment $c) => $c->getId(), $flat);
            $likedMap = $this->likeRepo->getUserLikedIds(new TargetType(TargetType::COMMENT), $commentIds, $viewerFingerprint);
        }

        // Hydrate viewer state on each node
        /** @var array<int, Comment> $map */
        $map = [];
        $tree = [];

        foreach ($flat as $comment) {
            $isLiked = isset($likedMap[$comment->getId()]);
            $isOwner = $viewerFingerprint !== null && hash_equals($comment->getFingerprintHash(), $viewerFingerprint);

            $comment->setLikedByViewer($isLiked);
            $comment->setOwnerViewer($isOwner);
            $map[$comment->getId()] = $comment;
        }

        // Build tree
        foreach ($flat as $comment) {
            $parentId = $comment->getParentId();
            if ($parentId !== null && isset($map[$parentId])) {
                $map[$parentId]->addChild($comment);
            } else {
                $tree[] = $comment;
            }
        }

        // Convert root comments and their children recursively to array
        return array_map(fn(Comment $c) => $c->toArray(), $tree);
    }

    public function deleteComment(int $id, ?string $ownershipToken, Identity $identity): bool
    {
        $comment = $this->commentRepo->findById($id);
        if ($comment === null) {
            throw new NotFoundException("Comment not found.");
        }

        $isAuthorByFingerprint = hash_equals($comment->getFingerprintHash(), $identity->getFingerprint());
        $isAuthorByToken = $ownershipToken !== null && $this->identityManager->verifyOwnership($ownershipToken, $comment->getOwnershipHash());

        if (!$isAuthorByFingerprint && !$isAuthorByToken) {
            throw new UnauthorizedException("You do not have permission to delete this comment.");
        }

        return $this->db->transaction(function () use ($comment, $id) {
            $deleted = $this->commentRepo->softDelete($id);
            // Decrement comments counter on post
            $this->postRepo->updateCounters($comment->getPostId(), 0, -1);

            if ($comment->getParentId() !== null) {
                $this->commentRepo->updateCounters($comment->getParentId(), 0, -1);
            }

            $this->cache->clear();
            return $deleted;
        });
    }
}
