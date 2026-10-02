<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Exception\NotFoundException;
use App\Core\Middleware\MiddlewareInterface;

class Router
{
    private array $routes = [];
    private array $globalMiddlewares = [];
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function use(MiddlewareInterface $middleware): self
    {
        $this->globalMiddlewares[] = $middleware;
        return $this;
    }

    public function get(string $path, array|callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, array|callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, array|callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, array|callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, array|callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    public function options(string $path, array|callable $handler, array $middlewares = []): self
    {
        return $this->addRoute('OPTIONS', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middlewares = []): self
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = "#^" . $pattern . "$#";

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // 1. Preflight OPTIONS request
        if ($method === 'OPTIONS') {
            $runner = $this->buildPipeline($this->globalMiddlewares, fn() => new Response('', 204));
            return $runner($request);
        }

        // 2. Find matching route
        $matchedRoute = null;
        $routeParams = [];

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $path, $matches)) {
                $matchedRoute = $route;
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $routeParams[$key] = $value;
                    }
                }
                break;
            }
        }

        if ($matchedRoute === null) {
            // Check if route exists with another method (405 Method Not Allowed)
            $allowedMethods = [];
            foreach ($this->routes as $route) {
                if (preg_match($route['pattern'], $path)) {
                    $allowedMethods[] = $route['method'];
                }
            }

            if (!empty($allowedMethods)) {
                $runner = $this->buildPipeline($this->globalMiddlewares, function () use ($allowedMethods) {
                    return Response::error("Method not allowed. Allowed methods: " . implode(', ', array_unique($allowedMethods)), 405);
                });
                return $runner($request);
            }

            throw new NotFoundException("Route not found: [{$method}] {$path}");
        }

        $request->setRouteParams($routeParams);

        // 3. Build middleware execution pipeline
        $allMiddlewares = array_merge($this->globalMiddlewares, $matchedRoute['middlewares']);
        $handler = $matchedRoute['handler'];

        $coreHandler = function (Request $req) use ($handler) {
            if (is_callable($handler)) {
                return $handler($req);
            }

            [$controllerClass, $methodName] = $handler;
            $controller = $this->container->get($controllerClass);
            return $controller->$methodName($req);
        };

        $pipeline = $this->buildPipeline($allMiddlewares, $coreHandler);
        return $pipeline($request);
    }

    private function buildPipeline(array $middlewares, callable $target): callable
    {
        return array_reduce(
            array_reverse($middlewares),
            function (callable $next, MiddlewareInterface $middleware) {
                return function (Request $request) use ($middleware, $next) {
                    return $middleware->process($request, $next);
                };
            },
            $target
        );
    }
}
