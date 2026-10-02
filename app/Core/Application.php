<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Cache\CacheInterface;
use App\Core\Cache\FileCache;
use App\Core\Exception\HttpException;
use App\Core\Middleware\CorsMiddleware;
use App\Core\Middleware\SecurityHeadersMiddleware;
use App\Domain\Repository\CommentRepositoryInterface;
use App\Domain\Repository\LikeRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\Repository\RateLimitRepositoryInterface;
use App\Http\Controller\CommentController;
use App\Http\Controller\DocsController;
use App\Http\Controller\IdentityController;
use App\Http\Controller\LikeController;
use App\Http\Controller\PostController;
use App\Http\Controller\SystemController;
use App\Infrastructure\Database\DatabaseSeeder;
use App\Infrastructure\Database\Migrator;
use App\Infrastructure\Repository\CommentRepository;
use App\Infrastructure\Repository\LikeRepository;
use App\Infrastructure\Repository\PostRepository;
use App\Infrastructure\Repository\RateLimitRepository;
use App\Infrastructure\Security\ContentModerator;
use App\Infrastructure\Security\IdentityManager;
use App\Infrastructure\Security\RateLimiter;
use App\Service\AnalyticsService;
use App\Service\CommentService;
use App\Service\LikeService;
use App\Service\PostService;
use Throwable;

class Application
{
    private Container $container;
    private Router $router;
    private array $config;
    private array $dbConfig;

    public function __construct(array $config, array $dbConfig)
    {
        $this->config = $config;
        $this->dbConfig = $dbConfig;
        $this->container = new Container();
        $this->router = new Router($this->container);

        $this->bootstrap();
    }

    private function bootstrap(): void
    {
        // 1. Register Core Configuration
        $this->container->set('config', $this->config);
        $this->container->set('db_config', $this->dbConfig);

        // 2. Register Database (MySQL / SQLite)
        $defaultConn = $this->dbConfig['default'] ?? 'mysql';
        $activeConfig = $this->dbConfig['connections'][$defaultConn] ?? reset($this->dbConfig['connections']);
        $this->container->singleton(Database::class, fn() => new Database($activeConfig));

        // 3. Register Cache
        $cacheDir = $this->config['cache_dir'] ?? (__DIR__ . '/../../storage/cache');
        $this->container->singleton(CacheInterface::class, fn() => new FileCache($cacheDir));

        // 4. Register Repositories
        $this->container->singleton(PostRepositoryInterface::class, PostRepository::class);
        $this->container->singleton(CommentRepositoryInterface::class, CommentRepository::class);
        $this->container->singleton(LikeRepositoryInterface::class, LikeRepository::class);
        $this->container->singleton(RateLimitRepositoryInterface::class, RateLimitRepository::class);

        // 5. Register Security & Moderation Services
        $appSecret = $this->config['app_secret'] ?? 'secret_key';
        $this->container->singleton(IdentityManager::class, fn() => new IdentityManager($appSecret));
        $this->container->singleton(ContentModerator::class, fn() => new ContentModerator($this->config['moderation'] ?? []));
        $this->container->singleton(RateLimiter::class, fn(Container $c) => new RateLimiter(
            $c->get(RateLimitRepositoryInterface::class),
            $this->config['rate_limits'] ?? []
        ));

        // 6. Register Domain Services
        $this->container->singleton(PostService::class, PostService::class);
        $this->container->singleton(CommentService::class, CommentService::class);
        $this->container->singleton(LikeService::class, LikeService::class);
        $this->container->singleton(AnalyticsService::class, AnalyticsService::class);

        // 7. Register Migrator & Seeder
        $this->container->singleton(Migrator::class, Migrator::class);
        $this->container->singleton(DatabaseSeeder::class, DatabaseSeeder::class);

        // 8. Register Middlewares & Routes
        $this->registerMiddlewares();
        $this->registerRoutes();
    }

    private function registerMiddlewares(): void
    {
        $this->router->use(new CorsMiddleware());
        $this->router->use(new SecurityHeadersMiddleware());
    }

    private function registerRoutes(): void
    {
        // --- Posts Endpoints ---
        $this->router->get('/api/posts', [PostController::class, 'index']);
        $this->router->post('/api/posts', [PostController::class, 'create']);
        $this->router->get('/api/posts/{id}', [PostController::class, 'show']);
        $this->router->delete('/api/posts/{id}', [PostController::class, 'delete']);

        // --- Comments Endpoints (Direct + Nested Replies) ---
        $this->router->post('/api/posts/{postId}/comments', [CommentController::class, 'create']);
        $this->router->post('/api/comments/{id}/replies', [CommentController::class, 'reply']);
        $this->router->delete('/api/comments/{id}', [CommentController::class, 'delete']);

        // --- Likes Endpoints (Polymorphic) ---
        $this->router->post('/api/likes/toggle', [LikeController::class, 'toggle']);
        $this->router->post('/api/likes/posts/{id}', [LikeController::class, 'likePost']);
        $this->router->post('/api/likes/comments/{id}', [LikeController::class, 'likeComment']);

        // --- Anonymous Identity Management ---
        $this->router->get('/api/identity/me', [IdentityController::class, 'me']);
        $this->router->post('/api/identity/refresh', [IdentityController::class, 'refresh']);

        // --- Telemetry, Health & Admin ---
        $this->router->get('/api/health', [SystemController::class, 'health']);
        $this->router->get('/api/stats', [SystemController::class, 'stats']);
        $this->router->post('/api/system/reset', [SystemController::class, 'resetDatabase']);

        // --- Interactive API Documentation (OpenAPI / Swagger) ---
        $this->router->get('/api/docs/openapi.json', [DocsController::class, 'openapi']);
        $this->router->get('/docs', [DocsController::class, 'ui']);

        // --- Static Asset Fallback Routes ---
        $this->router->get('/css/{file}', function (Request $request) {
            $file = basename((string)$request->getRouteParam('file'));
            $filePath = __DIR__ . '/../../public/css/' . $file;
            if (file_exists($filePath)) {
                return new Response(file_get_contents($filePath), 200, ['Content-Type' => 'text/css; charset=UTF-8']);
            }
            return Response::error('CSS file not found', 404);
        });

        $this->router->get('/js/{file}', function (Request $request) {
            $file = basename((string)$request->getRouteParam('file'));
            $filePath = __DIR__ . '/../../public/js/' . $file;
            if (file_exists($filePath)) {
                return new Response(file_get_contents($filePath), 200, ['Content-Type' => 'application/javascript; charset=UTF-8']);
            }
            return Response::error('JS file not found', 404);
        });

        // --- Frontend Single Page App Entry ---
        $this->router->get('/', function (Request $request) {
            $indexPath = __DIR__ . '/../../public/index.html';
            if (file_exists($indexPath)) {
                return new Response(file_get_contents($indexPath), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
            }
            return Response::success(['message' => 'Whisper Backend API is running. Explore /docs for endpoints.']);
        });
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            return Response::error($e->getMessage(), $e->getStatusCode(), $e->getErrors());
        } catch (Throwable $e) {
            $status = 500;
            $msg = ($this->config['debug'] ?? false)
                ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
                : 'An internal server error occurred';

            return Response::error($msg, $status, ($this->config['debug'] ?? false) ? ['trace' => explode("\n", $e->getTraceAsString())] : []);
        }
    }

    public function run(): void
    {
        $request = Request::createFromGlobals();
        $response = $this->handle($request);
        $response->send();
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
