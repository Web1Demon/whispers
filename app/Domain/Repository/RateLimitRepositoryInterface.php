<?php

declare(strict_types=1);

namespace App\Domain\Repository;

interface RateLimitRepositoryInterface
{
    public function recordHit(string $key, int $decaySeconds): int;
    public function getAttempts(string $key, int $decaySeconds): int;
    public function reset(string $key): void;
}
