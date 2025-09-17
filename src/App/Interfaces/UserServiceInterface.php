<?php

namespace Fawaz\App\Interfaces;

interface UserServiceInterface {
    public function setCurrentUserId(string $userId): void;
    public function profile(?array $args = []): array;
}