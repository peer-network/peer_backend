<?php

declare(strict_types=1);

namespace Fawaz\Utils;

class ErrorResponse
{
    public function __construct(public array $response)
    {
    }
}
