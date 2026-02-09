<?php

declare(strict_types=1);

namespace Fawaz\App\Errors;

class DomainException extends \RuntimeException implements HasResponseCode
{
    public function __construct(
        protected int $responseCode,
        string $message = '',
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getResponseCode(): int
    {
        return $this->responseCode;
    }
}
