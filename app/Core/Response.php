<?php

declare(strict_types=1);

namespace App\Core;

class Response
{
    private int $statusCode;
    private array $headers = [];
    private string|array|null $body;

    public function __construct(string|array|null $body = null, int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=UTF-8';
        return new self($data, $statusCode, $headers);
    }

    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200, array $meta = []): self
    {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return self::json($payload, $statusCode);
    }

    public static function paginate(array $items, int $page, int $limit, int $total, array $extra = []): self
    {
        $totalPages = (int)ceil($total / max(1, $limit));
        return self::json([
            'success' => true,
            'data' => $items,
            'pagination' => array_merge([
                'page' => $page,
                'limit' => $limit,
                'total_items' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
            ], $extra)
        ]);
    }

    public static function error(string $message, int $statusCode = 400, array $errors = []): self
    {
        $payload = [
            'success' => false,
            'error' => [
                'code' => $statusCode,
                'message' => $message,
            ]
        ];

        if (!empty($errors)) {
            $payload['error']['details'] = $errors;
        }

        return self::json($payload, $statusCode);
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string|array|null
    {
        return $this->body;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
        }

        if (is_array($this->body)) {
            echo json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        } elseif ($this->body !== null) {
            echo (string)$this->body;
        }
    }
}
