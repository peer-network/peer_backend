<?php

declare(strict_types=1);

namespace Fawaz\App;

use RuntimeException;
use Throwable;

class ValidationException extends RuntimeException
{
    public function __construct(string $message = "", protected array $errors = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
