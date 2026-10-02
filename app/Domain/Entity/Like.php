<?php

declare(strict_types=1);

namespace App\Domain\Entity;

class Like
{
    private ?int $id;
    private string $targetType;
    private int $targetId;
    private string $userFingerprint;
    private string $createdAt;

    public function __construct(
        ?int $id,
        string $targetType,
        int $targetId,
        string $userFingerprint,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->userFingerprint = $userFingerprint;
        $this->createdAt = $createdAt ?? date('Y-m-d H:i:s');
    }

    public static function fromArray(array $data): self
    {
        return new self(
            isset($data['id']) ? (int)$data['id'] : null,
            $data['target_type'] ?? 'post',
            (int)($data['target_id'] ?? 0),
            $data['user_fingerprint'] ?? '',
            $data['created_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'user_fingerprint' => $this->userFingerprint,
            'created_at' => $this->createdAt,
        ];
    }

    public function getId(): ?int { return $this->id; }
    public function getTargetType(): string { return $this->targetType; }
    public function getTargetId(): int { return $this->targetId; }
    public function getUserFingerprint(): string { return $this->userFingerprint; }
    public function getCreatedAt(): string { return $this->createdAt; }
}
