<?php

namespace Fawaz\App\Interfaces;

use Fawaz\App\Profile;

interface UserServiceInterface {
    public function profile(?array $args = []): array;
}