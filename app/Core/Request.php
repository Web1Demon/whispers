<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    private string $method;
    private string $uri;
    private string $path;
    private array $queryParams;
    private array $bodyParams;
    private array $headers;
    private array $server;
    private array $routeParams = [];
    private ?string $rawBody = null;

    public function __construct(
        string $method,
        string $uri,
        array $queryParams = [],
        array $bodyParams = [],
        array $headers = [],
        array $server = [],
        ?string $rawBody = null
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $this->queryParams = $queryParams;
        $this->bodyParams = $bodyParams;
        $this->headers = $this->normalizeHeaders($headers);
        $this->server = $server;
        $this->rawBody = $rawBody;
    }

    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $queryParams = $_GET;
        $rawBody = file_get_contents('php://input') ?: null;
        $bodyParams = [];

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains(strtolower($contentType), 'application/json') && $rawBody !== null) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $bodyParams = $decoded;
            }
        } else {
            $bodyParams = $_POST;
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headerName = str_replace('_', '-', strtolower($key));
                $headers[$headerName] = $value;
            }
        }

        return new self($method, $uri, $queryParams, $bodyParams, $headers, $_SERVER, $rawBody);
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower(str_replace('_', '-', (string)$key))] = (string)$value;
        }
        return $normalized;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getQuery(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function getBody(): array
    {
        return $this->bodyParams;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->bodyParams[$key] ?? $this->queryParams[$key] ?? $default;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, ?string $default = null): ?string
    {
        $name = strtolower(str_replace('_', '-', $name));
        return $this->headers[$name] ?? $default;
    }

    public function getClientIp(): string
    {
        $headersToCheck = [
            'http-cf-connecting-ip',
            'http-x-real-ip',
            'http-x-forwarded-for',
            'x-forwarded-for',
            'x-real-ip',
        ];

        foreach ($headersToCheck as $header) {
            $val = $this->getHeader($header);
            if ($val !== null && trim($val) !== '') {
                $ips = explode(',', $val);
                return trim($ips[0]);
            }
        }

        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function getUserAgent(): string
    {
        return $this->getHeader('user-agent') ?? 'unknown';
    }

    public function getAnonymousToken(): ?string
    {
        return $this->getHeader('x-anonymous-token') 
            ?? $this->getHeader('x-auth-token') 
            ?? $this->getQuery('token')
            ?? null;
    }

    public function getOwnershipToken(): ?string
    {
        return $this->getHeader('x-ownership-token')
            ?? $this->get('ownership_token')
            ?? null;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function getRouteParam(string $name, mixed $default = null): mixed
    {
        return $this->routeParams[$name] ?? $default;
    }

    public function getRouteParams(): array
    {
        return $this->routeParams;
    }

    public function getRawBody(): ?string
    {
        return $this->rawBody;
    }
}
