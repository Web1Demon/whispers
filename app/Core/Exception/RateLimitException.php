<?php

declare(strict_types=1);

namespace App\Core\Exception;

class RateLimitException extends HttpException
{
    private int $retryAfter;

    public function __construct(string $message = "Too many requests. Please slow down.", int $retryAfter = 60)
    {
        $this->retryAfter = $retryAfter;
        parent::__construct($message, 429, ['retry_after_seconds' => $retryAfter]);
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
