<?php

declare(strict_types=1);

namespace Fawaz\App\ReadModels;

class UserRef
{
    public function __construct(
        private string $key,
        private string $userId
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function userId(): string
    {
        return $this->userId;
    }
}
