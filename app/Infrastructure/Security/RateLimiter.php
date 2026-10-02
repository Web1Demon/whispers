<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Core\Exception\RateLimitException;
use App\Domain\Repository\RateLimitRepositoryInterface;

class RateLimiter
{
    private RateLimitRepositoryInterface $repository;
    private array $rules;

    public function __construct(RateLimitRepositoryInterface $repository, array $rules = [])
    {
        $this->repository = $repository;
        $this->rules = $rules;
    }

    public function check(string $action, string $identifier): void
    {
        $rule = $this->rules[$action] ?? ['max_attempts' => 60, 'decay_seconds' => 60];
        $maxAttempts = (int)($rule['max_attempts'] ?? 60);
        $decaySeconds = (int)($rule['decay_seconds'] ?? 60);

        $key = "rl:{$action}:{$identifier}";
        $attempts = $this->repository->recordHit($key, $decaySeconds);

        if ($attempts > $maxAttempts) {
            throw new RateLimitException(
                "Rate limit exceeded for action '{$action}'. Please wait before retrying.",
                $decaySeconds
            );
        }
    }

    public function getRemainingAttempts(string $action, string $identifier): int
    {
        $rule = $this->rules[$action] ?? ['max_attempts' => 60, 'decay_seconds' => 60];
        $maxAttempts = (int)($rule['max_attempts'] ?? 60);
        $decaySeconds = (int)($rule['decay_seconds'] ?? 60);

        $key = "rl:{$action}:{$identifier}";
        $attempts = $this->repository->getAttempts($key, $decaySeconds);

        return max(0, $maxAttempts - $attempts);
    }
}
