<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Cache\CacheInterface;
use App\Core\Database;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\UnauthorizedException;
use App\Domain\Entity\Identity;
use App\Domain\Entity\Post;
use App\Domain\Repository\LikeRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\ValueObject\FeedSort;
use App\Domain\ValueObject\TargetType;
use App\Infrastructure\Security\ContentModerator;
use App\Infrastructure\Security\IdentityManager;
use App\Infrastructure\Security\RateLimiter;

class PostService
{
    private PostRepositoryInterface $postRepo;
    private LikeRepositoryInterface $likeRepo;
    private IdentityManager $identityManager;
    private ContentModerator $moderator;
    private RateLimiter $rateLimiter;
    private CacheInterface $cache;
    private Database $db;

    public function __construct(
        PostRepositoryInterface $postRepo,
        LikeRepositoryInterface $likeRepo,
        IdentityManager $identityManager,
        ContentModerator $moderator,
        RateLimiter $rateLimiter,
        CacheInterface $cache,
        Database $db
    ) {
        $this->postRepo = $postRepo;
        $this->likeRepo = $likeRepo;
        $this->identityManager = $identityManager;
        $this->moderator = $moderator;
        $this->rateLimiter = $rateLimiter;
        $this->cache = $cache;
        $this->db = $db;
    }

    public function createPost(string $title, string $content, string $category, Identity $identity): array
    {
        // 1. Rate limiting check
        $this->rateLimiter->check('posts', $identity->getFingerprint());

        // 2. Content validation and sanitization
        $clean = $this->moderator->validatePost($title, $content, $category);

        // 3. Generate UID and temporary ownership token
        $uid = bin2hex(random_bytes(6));
        $now = date('Y-m-d H:i:s');
        $ownershipToken = $this->identityManager->generateOwnershipToken('post', $uid, $identity->getFingerprint(), $now);

        $post = new Post(
            null,
            $uid,
            $clean['title'],
            $clean['content'],
            $clean['category'],
            $identity->getPseudonym(),
            $identity->getAvatarGradientFrom(),
            $identity->getAvatarGradientTo(),
            $identity->getFingerprint(),
            $ownershipToken,
            0,
            0,
            0,
            0.0,
            false,
            false,
            $now,
            $now
        );

        $savedPost = $this->postRepo->create($post);
        $this->cache->clear(); // Invalidate feed caches

        $postArray = $savedPost->toArray();
        $postArray['viewer_state']['is_owner'] = true;
        $postArray['viewer_state']['has_liked'] = false;

        return [
            'post' => $postArray,
            'ownership_token' => $ownershipToken,
        ];
    }

    public function getFeed(
        int $page = 1,
        int $limit = 15,
        ?string $sort = 'recent',
        ?string $category = null,
        ?string $search = null,
        ?Identity $viewerIdentity = null
    ): array {
        $feedSort = new FeedSort($sort);
        $posts = $this->postRepo->getFeed($page, $limit, $feedSort, $category, $search);
        $total = $this->postRepo->countFeed($category, $search);

        if (empty($posts)) {
            return [
                'items' => [],
                'total' => $total,
            ];
        }

        // Batch fetch viewer liked state in O(1) query
        $postIds = array_map(fn(Post $p) => $p->getId(), $posts);
        $likedMap = [];
        $viewerFingerprint = $viewerIdentity?->getFingerprint();

        if ($viewerFingerprint !== null) {
            $likedMap = $this->likeRepo->getUserLikedIds(new TargetType(TargetType::POST), $postIds, $viewerFingerprint);
        }

        $items = [];
        foreach ($posts as $post) {
            $isLiked = isset($likedMap[$post->getId()]);
            $isOwner = $viewerFingerprint !== null && hash_equals($post->getFingerprintHash(), $viewerFingerprint);

            $post->setLikedByViewer($isLiked);
            $post->setOwnerViewer($isOwner);
            $items[] = $post->toArray();
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function getPost(string|int $idOrUid, ?Identity $viewerIdentity = null, bool $incrementView = true): Post
    {
        $post = is_numeric($idOrUid)
            ? $this->postRepo->findById((int)$idOrUid)
            : $this->postRepo->findByUid((string)$idOrUid);

        if ($post === null || $post->isDeleted()) {
            throw new NotFoundException("Whisper post not found or has been removed.");
        }

        if ($incrementView) {
            $this->postRepo->incrementViews($post->getId());
        }

        $viewerFingerprint = $viewerIdentity?->getFingerprint();
        if ($viewerFingerprint !== null) {
            $isLiked = $this->likeRepo->exists(new TargetType(TargetType::POST), $post->getId(), $viewerFingerprint);
            $isOwner = hash_equals($post->getFingerprintHash(), $viewerFingerprint);
            $post->setLikedByViewer($isLiked);
            $post->setOwnerViewer($isOwner);
        }

        return $post;
    }

    public function deletePost(int $id, ?string $ownershipToken, Identity $identity): bool
    {
        $post = $this->postRepo->findById($id);
        if ($post === null) {
            throw new NotFoundException("Post not found.");
        }

        $isAuthorByFingerprint = hash_equals($post->getFingerprintHash(), $identity->getFingerprint());
        $isAuthorByToken = $ownershipToken !== null && $this->identityManager->verifyOwnership($ownershipToken, $post->getOwnershipHash());

        if (!$isAuthorByFingerprint && !$isAuthorByToken) {
            throw new UnauthorizedException("You do not have permission to delete this whisper.");
        }

        $deleted = $this->postRepo->softDelete($id);
        $this->cache->clear();
        return $deleted;
    }
}
