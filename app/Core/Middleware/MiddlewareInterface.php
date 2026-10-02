<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;

interface MiddlewareInterface
{
    /**
     * Process an incoming server request and return a response, or delegate to the next handler
     *
     * @param Request $request
     * @param callable $next fn(Request $req): Response
     * @return Response
     */
    public function process(Request $request, callable $next): Response;
}
