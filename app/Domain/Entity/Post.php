<?php

declare(strict_types=1);

namespace App\Domain\Entity;

class Post
{
    private ?int $id;
    private string $uid;
    private string $title;
    private string $content;
    private string $category;
    private string $authorPseudonym;
    private string $authorAvatarFrom;
    private string $authorAvatarTo;
    private string $fingerprintHash;
    private string $ownershipHash;
    private int $likesCount;
    private int $commentsCount;
    private int $viewsCount;
    private float $hotScore;
    private bool $isPinned;
    private bool $isDeleted;
    private string $createdAt;
    private string $updatedAt;
    private ?bool $isLikedByViewer = null;
    private ?bool $isOwnerViewer = null;

    public function __construct(
        ?int $id,
        string $uid,
        string $title,
        string $content,
        string $category,
        string $authorPseudonym,
        string $authorAvatarFrom,
        string $authorAvatarTo,
        string $fingerprintHash,
        string $ownershipHash,
        int $likesCount = 0,
        int $commentsCount = 0,
        int $viewsCount = 0,
        float $hotScore = 0.0,
        bool $isPinned = false,
        bool $isDeleted = false,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->uid = $uid;
        $this->title = $title;
        $this->content = $content;
        $this->category = $category;
        $this->authorPseudonym = $authorPseudonym;
        $this->authorAvatarFrom = $authorAvatarFrom;
        $this->authorAvatarTo = $authorAvatarTo;
        $this->fingerprintHash = $fingerprintHash;
        $this->ownershipHash = $ownershipHash;
        $this->likesCount = $likesCount;
        $this->commentsCount = $commentsCount;
        $this->viewsCount = $viewsCount;
        $this->hotScore = $hotScore;
        $this->isPinned = $isPinned;
        $this->isDeleted = $isDeleted;
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
        $this->updatedAt = $updatedAt ?? date('Y-m-d H:i:s');
    }

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['id']) ? (int)$data['id'] : null,
            $data['uid'] ?? bin2hex(random_bytes(8)),
            $data['title'] ?? '',
            $data['content'] ?? '',
            $data['category'] ?? 'general',
            $data['author_pseudonym'] ?? 'Anonymous',
            $data['author_avatar_from'] ?? '#6366f1',
            $data['author_avatar_to'] ?? '#a855f7',
            $data['fingerprint_hash'] ?? '',
            $data['ownership_hash'] ?? '',
            (int)($data['likes_count'] ?? 0),
            (int)($data['comments_count'] ?? 0),
            (int)($data['views_count'] ?? 0),
            (float)($data['hot_score'] ?? 0.0),
            (bool)($data['is_pinned'] ?? false),
            (bool)($data['is_deleted'] ?? false),
            $data['created_at'] ?? null,
            $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'uid' => $this->uid,
            'title' => $this->title,
            'content' => $this->isDeleted ? '[This whisper was deleted by its author]' : $this->content,
            'category' => $this->category,
            'author' => [
                'pseudonym' => $this->authorPseudonym,
                'avatar_gradient' => [
                    'from' => $this->authorAvatarFrom,
                    'to' => $this->authorAvatarTo,
                ],
            ],
            'metrics' => [
                'likes_count' => $this->likesCount,
                'comments_count' => $this->commentsCount,
                'views_count' => $this->viewsCount,
                'hot_score' => $this->hotScore,
            ],
            'is_pinned' => $this->isPinned,
            'is_deleted' => $this->isDeleted,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'viewer_state' => [
                'has_liked' => $this->isLikedByViewer,
                'is_owner' => $this->isOwnerViewer,
            ],
        ];
    }

    // Getters & Setters
    public function getId(): ?int { return $this->id; }
    public function getUid(): string { return $this->uid; }
    public function getTitle(): string { return $this->title; }
    public function getContent(): string { return $this->content; }
    public function getCategory(): string { return $this->category; }
    public function getAuthorPseudonym(): string { return $this->authorPseudonym; }
    public function getAuthorAvatarFrom(): string { return $this->authorAvatarFrom; }
    public function getAuthorAvatarTo(): string { return $this->authorAvatarTo; }
    public function getFingerprintHash(): string { return $this->fingerprintHash; }
    public function getOwnershipHash(): string { return $this->ownershipHash; }
    public function getLikesCount(): int { return $this->likesCount; }
    public function getCommentsCount(): int { return $this->commentsCount; }
    public function getViewsCount(): int { return $this->viewsCount; }
    public function getHotScore(): float { return $this->hotScore; }
    public function isPinned(): bool { return $this->isPinned; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }

    public function setLikedByViewer(?bool $liked): void { $this->isLikedByViewer = $liked; }
    public function setOwnerViewer(?bool $owner): void { $this->isOwnerViewer = $owner; }
}
