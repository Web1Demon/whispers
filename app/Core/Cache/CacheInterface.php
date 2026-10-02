<?php

declare(strict_types=1);

namespace App\Core\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $ttlSeconds = 300): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function remember(string $key, int $ttlSeconds, callable $callback): mixed;
}
