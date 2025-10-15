<?php

namespace Fawaz\App\Interfaces;

interface ProfileService {
    public function setCurrentUserId(string $userId): void;
    public function profile(?array $args = []): array;
}