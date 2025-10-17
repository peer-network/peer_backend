<?php

namespace Fawaz\App\Interfaces;

use Fawaz\App\Profile;
use Fawaz\Utils\ErrorResponse;

interface ProfileService {
    public function setCurrentUserId(string $userId): void;
    public function profile(array $args): Profile | ErrorResponse;
}