<?php

declare(strict_types=1);

namespace Fawaz\App\Validation;

class ValidatorErrors
{
    public function __construct(
        public array $errors
    ) {
    }
}
