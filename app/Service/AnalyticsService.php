<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Cache\FileCache;
use App\Core\Database;
use App\Domain\Repository\PostRepositoryInterface;

class AnalyticsService
{
    private PostRepositoryInterface $postRepo;
    private Database $db;

    public function __construct(PostRepositoryInterface $postRepo, Database $db)
    {
        $this->postRepo = $postRepo;
        $this->db = $db;
    }

    public function getSystemStats(): array
    {
        $stats = $this->postRepo->getTotalStats();
        $categories = $this->postRepo->getCategories();
        $cacheStats = FileCache::getStats();

        return [
            'platform' => [
                'total_posts' => (int)($stats['total_posts'] ?? 0),
                'total_likes' => (int)($stats['total_likes'] ?? 0),
                'total_comments' => (int)($stats['total_comments'] ?? 0),
                'total_views' => (int)($stats['total_views'] ?? 0),
                'categories' => $categories,
            ],
            'database' => [
                'driver' => $this->db->getDriver(),
                'status' => 'connected',
            ],
            'cache' => $cacheStats,
            'server' => [
                'php_version' => PHP_VERSION,
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'uptime' => date('c'),
            ],
        ];
    }
}
