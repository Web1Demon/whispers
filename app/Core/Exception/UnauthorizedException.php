<?php

declare(strict_types=1);

namespace App\Core\Exception;

class UnauthorizedException extends HttpException
{
    public function __construct(string $message = "Unauthorized action or invalid ownership token", array $errors = [])
    {
        parent::__construct($message, 403, $errors);
    }
}
