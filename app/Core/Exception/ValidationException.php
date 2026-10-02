<?php

declare(strict_types=1);

namespace App\Core\Exception;

class ValidationException extends HttpException
{
    public function __construct(string $message = "Validation failed", array $errors = [])
    {
        parent::__construct($message, 422, $errors);
    }
}
