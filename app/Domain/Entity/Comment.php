<?php

declare(strict_types=1);

namespace App\Domain\Entity;

class Comment
{
    private ?int $id;
    private int $postId;
    private ?int $parentId;
    private int $depth;
    private string $path;
    private string $content;
    private string $authorPseudonym;
    private string $authorAvatarFrom;
    private string $authorAvatarTo;
    private string $fingerprintHash;
    private string $ownershipHash;
    private int $likesCount;
    private int $repliesCount;
    private bool $isDeleted;
    private string $createdAt;
    private string $updatedAt;
    private array $children = [];
    private ?bool $isLikedByViewer = null;
    private ?bool $isOwnerViewer = null;
    private ?string $parentAuthorPseudonym = null;

    public function __construct(
        ?int $id,
        int $postId,
        ?int $parentId,
        int $depth,
        string $path,
        string $content,
        string $authorPseudonym,
        string $authorAvatarFrom,
        string $authorAvatarTo,
        string $fingerprintHash,
        string $ownershipHash,
        int $likesCount = 0,
        int $repliesCount = 0,
        bool $isDeleted = false,
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->id = $id;
        $this->postId = $postId;
        $this->parentId = $parentId;
        $this->depth = $depth;
        $this->path = $path;
        $this->content = $content;
        $this->authorPseudonym = $authorPseudonym;
        $this->authorAvatarFrom = $authorAvatarFrom;
        $this->authorAvatarTo = $authorAvatarTo;
        $this->fingerprintHash = $fingerprintHash;
        $this->ownershipHash = $ownershipHash;
        $this->likesCount = $likesCount;
        $this->repliesCount = $repliesCount;
        $this->isDeleted = $isDeleted;
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
        $this->updatedAt = $updatedAt ?? date('Y-m-d H:i:s');
    }

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['id']) ? (int)$data['id'] : null,
            (int)($data['post_id'] ?? 0),
            isset($data['parent_id']) && $data['parent_id'] !== null ? (int)$data['parent_id'] : null,
            (int)($data['depth'] ?? 0),
            $data['path'] ?? '',
            $data['content'] ?? '',
            $data['author_pseudonym'] ?? 'Anonymous',
            $data['author_avatar_from'] ?? '#6366f1',
            $data['author_avatar_to'] ?? '#a855f7',
            $data['fingerprint_hash'] ?? '',
            $data['ownership_hash'] ?? '',
            (int)($data['likes_count'] ?? 0),
            (int)($data['replies_count'] ?? 0),
            (bool)($data['is_deleted'] ?? false),
            $data['created_at'] ?? null,
            $data['updated_at'] ?? null
        );
    }

    public function toArray(): array
    {
        $childrenArray = [];
        foreach ($this->children as $child) {
            $childrenArray[] = $child instanceof self ? $child->toArray() : $child;
        }

        return [
            'id' => $this->id,
            'post_id' => $this->postId,
            'parent_id' => $this->parentId,
            'parent_author' => $this->parentAuthorPseudonym,
            'depth' => $this->depth,
            'path' => $this->path,
            'content' => $this->isDeleted ? '[This comment was deleted by its author]' : $this->content,
            'author' => [
                'pseudonym' => $this->authorPseudonym,
                'avatar_gradient' => [
                    'from' => $this->authorAvatarFrom,
                    'to' => $this->authorAvatarTo,
                ],
            ],
            'metrics' => [
                'likes_count' => $this->likesCount,
                'replies_count' => $this->repliesCount,
            ],
            'is_deleted' => $this->isDeleted,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'viewer_state' => [
                'has_liked' => $this->isLikedByViewer,
                'is_owner' => $this->isOwnerViewer,
            ],
            'children' => $childrenArray,
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getPostId(): int { return $this->postId; }
    public function getParentId(): ?int { return $this->parentId; }
    public function getDepth(): int { return $this->depth; }
    public function getPath(): string { return $this->path; }
    public function getContent(): string { return $this->content; }
    public function getAuthorPseudonym(): string { return $this->authorPseudonym; }
    public function getAuthorAvatarFrom(): string { return $this->authorAvatarFrom; }
    public function getAuthorAvatarTo(): string { return $this->authorAvatarTo; }
    public function getFingerprintHash(): string { return $this->fingerprintHash; }
    public function getOwnershipHash(): string { return $this->ownershipHash; }
    public function getLikesCount(): int { return $this->likesCount; }
    public function getRepliesCount(): int { return $this->repliesCount; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function getCreatedAt(): string { return $this->createdAt; }
    public function getUpdatedAt(): string { return $this->updatedAt; }
    public function getChildren(): array { return $this->children; }

    public function addChild(self $child): void { $this->children[] = $child; }
    public function setChildren(array $children): void { $this->children = $children; }
    public function setLikedByViewer(?bool $liked): void { $this->isLikedByViewer = $liked; }
    public function setOwnerViewer(?bool $owner): void { $this->isOwnerViewer = $owner; }
    public function setParentAuthorPseudonym(?string $name): void { $this->parentAuthorPseudonym = $name; }
}
