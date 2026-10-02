<?php

declare(strict_types=1);

namespace App\Core\Exception;

use Exception;

class HttpException extends Exception
{
    protected int $statusCode;
    protected array $errors;

    public function __construct(string $message = "", int $statusCode = 500, array $errors = [], ?Exception $previous = null)
    {
        $this->statusCode = $statusCode;
        $this->errors = $errors;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
