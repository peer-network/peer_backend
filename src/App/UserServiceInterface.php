<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\Utils\ErrorResponse;

interface UserServiceInterface
{
    public function setCurrentUserId(string $userId): void;

    public function loadVisibleUsersById(string $userId): User|false;

    public function isVisibleUserExistById(string $userId): bool;

    public function loadAllUsersById(string $userId): User|false;

    public function isAnyUserExistById(string $userId): bool;

    public function createUser(array $args): array;

    public function verifyReferral(string $referralString): array;

    public function referralList(string $userId, int $offset = 0, int $limit = 20): array;

    public function verifyAccount(string $userId): array;

    public function deleteUnverifiedUsers(): bool|array;

    public function updateUserPreferences(?array $args = []): array;

    public function setPassword(?array $args = []): array;

    public function setEmail(?array $args = []): array;

    public function setUsername(?array $args = []): array;

    public function deleteAccount(string $expassword): array;

    public function Follows(?array $args = []): array | ErrorResponse;

    public function getFriends(?array $args = []): array|null;

    public function getAllFriends(?array $args = []): array|null;

    public function fetchAllAdvance(?array $args = []): array;

    public function fetchAll(?array $args = []): array;

    public function requestPasswordReset(string $email): array;

    public function resetPasswordTokenVerify(string $token): array;

    public function genericPasswordResetSuccessResponse(array $passwordAttempt = []): array;

    public function calculateNextAttemptDelay(array $passwordAttempt = []): string;

    public function resetPassword(?array $args): array;
}
