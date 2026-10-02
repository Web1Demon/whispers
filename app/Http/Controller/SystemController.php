<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Database\DatabaseSeeder;
use App\Infrastructure\Database\Migrator;
use App\Service\AnalyticsService;

class SystemController
{
    private AnalyticsService $analyticsService;
    private Migrator $migrator;
    private DatabaseSeeder $seeder;

    public function __construct(
        AnalyticsService $analyticsService,
        Migrator $migrator,
        DatabaseSeeder $seeder
    ) {
        $this->analyticsService = $analyticsService;
        $this->migrator = $migrator;
        $this->seeder = $seeder;
    }

    public function health(Request $request): Response
    {
        return Response::success([
            'status' => 'healthy',
            'timestamp' => date('c'),
            'service' => 'Whisper Backend Engine',
            'version' => '1.0.0',
        ], 'System is healthy');
    }

    public function stats(Request $request): Response
    {
        $stats = $this->analyticsService->getSystemStats();
        return Response::success($stats, 'Platform metrics retrieved');
    }

    public function resetDatabase(Request $request): Response
    {
        $this->migrator->reset();
        $this->seeder->seed();
        return Response::success(null, 'Database migrated and seeded with sample data successfully');
    }
}
