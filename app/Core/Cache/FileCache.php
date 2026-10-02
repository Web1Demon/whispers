<?php

declare(strict_types=1);

namespace App\Core\Cache;

class FileCache implements CacheInterface
{
    private string $cacheDir;
    private static int $hits = 0;
    private static int $misses = 0;

    public function __construct(string $cacheDir)
    {
        $this->cacheDir = rtrim($cacheDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0777, true);
        }
    }

    private function getFilePath(string $key): string
    {
        $hash = sha1($key);
        return $this->cacheDir . $hash . '.cache';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            self::$misses++;
            return $default;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            self::$misses++;
            return $default;
        }

        $data = @unserialize($raw);
        if ($data === false || !isset($data['expires_at'], $data['value'])) {
            self::$misses++;
            @unlink($file);
            return $default;
        }

        if (time() > $data['expires_at']) {
            self::$misses++;
            @unlink($file);
            return $default;
        }

        self::$hits++;
        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 300): bool
    {
        $file = $this->getFilePath($key);
        $payload = [
            'expires_at' => time() + $ttlSeconds,
            'value' => $value,
        ];

        return @file_put_contents($file, serialize($payload), LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return @unlink($file);
        }
        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->cacheDir . '*.cache');
        if ($files === false) {
            return true;
        }
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        return true;
    }

    public function remember(string $key, int $ttlSeconds, callable $callback): mixed
    {
        $val = $this->get($key);
        if ($val !== null) {
            return $val;
        }

        $val = $callback();
        $this->set($key, $val, $ttlSeconds);
        return $val;
    }

    public static function getStats(): array
    {
        $total = self::$hits + self::$misses;
        $ratio = $total > 0 ? round((self::$hits / $total) * 100, 2) : 100.0;
        return [
            'hits' => self::$hits,
            'misses' => self::$misses,
            'total_requests' => $total,
            'hit_ratio_percent' => $ratio,
        ];
    }
}
