<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;

class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response('', 204);
            $this->applyHeaders($response);
            return $response;
        }

        $response = $next($request);
        $this->applyHeaders($response);
        return $response;
    }

    private function applyHeaders(Response $response): void
    {
        $response->setHeader('Access-Control-Allow-Origin', '*');
        $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Anonymous-Token, X-Ownership-Token');
        $response->setHeader('Access-Control-Max-Age', '86400');
    }
}
