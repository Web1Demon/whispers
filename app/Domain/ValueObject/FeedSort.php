<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

class FeedSort
{
    public const RECENT = 'recent';
    public const HOT = 'hot';
    public const POPULAR = 'popular';
    public const DISCUSSED = 'discussed';

    private string $value;

    public function __construct(?string $value = self::RECENT)
    {
        $normalized = strtolower(trim((string)$value));
        if (!in_array($normalized, [self::RECENT, self::HOT, self::POPULAR, self::DISCUSSED], true)) {
            $normalized = self::RECENT;
        }
        $this->value = $normalized;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getOrderBySql(): string
    {
        return match ($this->value) {
            self::HOT => 'hot_score DESC, created_at DESC',
            self::POPULAR => 'likes_count DESC, created_at DESC',
            self::DISCUSSED => 'comments_count DESC, created_at DESC',
            default => 'is_pinned DESC, created_at DESC',
        };
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
