<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

class TargetType
{
    public const POST = 'post';
    public const COMMENT = 'comment';

    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, [self::POST, self::COMMENT], true)) {
            throw new InvalidArgumentException("Invalid target type [{$value}]. Allowed: post, comment.");
        }
        $this->value = $normalized;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isPost(): bool
    {
        return $this->value === self::POST;
    }

    public function isComment(): bool
    {
        return $this->value === self::COMMENT;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
