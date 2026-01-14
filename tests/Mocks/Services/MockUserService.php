<?php

declare(strict_types=1);

namespace Tests\Mocks\Services;

use Fawaz\App\User;
use Fawaz\App\UserServiceInterface;
use Fawaz\Utils\ErrorResponse;

final class MockUserService implements UserServiceInterface
{
    private array $responses = [];
    public array $calls = [];

    public function setResponse(string $method, mixed $response): void
    {
        $this->responses[$method] = $response;
    }

    private function getResponse(string $method, mixed $default = null): mixed
    {
        return $this->responses[$method] ?? $default;
    }

    private function recordCall(string $method, array $arguments = []): void
    {
        $this->calls[] = [
            'method' => $method,
            'args' => $arguments,
        ];
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->recordCall(__FUNCTION__, [$userId]);
    }

    public function loadVisibleUsersById(string $userId): User|false
    {
        $this->recordCall(__FUNCTION__, [$userId]);
        return $this->getResponse(__FUNCTION__, false);
    }

    public function isVisibleUserExistById(string $userId): bool
    {
        $this->recordCall(__FUNCTION__, [$userId]);
        return (bool) $this->getResponse(__FUNCTION__, false);
    }

    public function loadAllUsersById(string $userId): User|false
    {
        $this->recordCall(__FUNCTION__, [$userId]);
        return $this->getResponse(__FUNCTION__, false);
    }

    public function isAnyUserExistById(string $userId): bool
    {
        $this->recordCall(__FUNCTION__, [$userId]);
        return (bool) $this->getResponse(__FUNCTION__, false);
    }

    public function createUser(array $args): array
    {
        $this->recordCall(__FUNCTION__, [$args]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function verifyReferral(string $referralString): array
    {
        $this->recordCall(__FUNCTION__, [$referralString]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function referralList(string $userId, int $offset = 0, int $limit = 20): array
    {
        $this->recordCall(__FUNCTION__, [$userId, $offset, $limit]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function verifyAccount(string $userId): array
    {
        $this->recordCall(__FUNCTION__, [$userId]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function deleteUnverifiedUsers(): bool|array
    {
        $this->recordCall(__FUNCTION__);
        return $this->getResponse(__FUNCTION__, false);
    }

    public function updateUserPreferences(?array $args = []): array
    {
        $this->recordCall(__FUNCTION__, [$args]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function setPassword(?array $args = []): array
    {
        $this->recordCall(__FUNCTION__, [$args]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function setEmail(?array $args = []): array
    {
        $this->recordCall(__FUNCTION__, [$args]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function setUsername(?array $args = []): array
    {
        $this->recordCall(__FUNCTION__, [$args]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function deleteAccount(string $expassword): array
    {
        $this->recordCall(__FUNCTION__, [$expassword]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function Follows(?array $args = []): array | ErrorResponse
    {
        $this->recordCall(__FUNCTION__, [$args]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function getFriends(?array $args = []): array|null
    {
        $this->recordCall(__FUNCTION__, [$args]);
        return $this->getResponse(__FUNCTION__, null);
    }

    public function getAllFriends(?array $args = []): array|null
    {
        $this->recordCall(__FUNCTION__, [$args]);
        return $this->getResponse(__FUNCTION__, null);
    }

    public function fetchAllAdvance(?array $args = []): array
    {
        $this->recordCall(__FUNCTION__, [$args]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function fetchAll(?array $args = []): array
    {
        $this->recordCall(__FUNCTION__, [$args]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function requestPasswordReset(string $email): array
    {
        $this->recordCall(__FUNCTION__, [$email]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function resetPasswordTokenVerify(string $token): array
    {
        $this->recordCall(__FUNCTION__, [$token]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function genericPasswordResetSuccessResponse(array $passwordAttempt = []): array
    {
        $this->recordCall(__FUNCTION__, [$passwordAttempt]);
        return $this->getResponse(__FUNCTION__, []);
    }

    public function calculateNextAttemptDelay(array $passwordAttempt = []): string
    {
        $this->recordCall(__FUNCTION__, [$passwordAttempt]);
        return (string) $this->getResponse(__FUNCTION__, '');
    }

    public function resetPassword(?array $args): array
    {
        $this->recordCall(__FUNCTION__, [$args]);
        return $this->getResponse(__FUNCTION__, []);
    }
}
