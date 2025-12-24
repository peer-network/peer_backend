<?php

declare(strict_types=1);

namespace Fawaz\App\Errors;

interface HasResponseCode {
    public function getResponseCode(): int;
}