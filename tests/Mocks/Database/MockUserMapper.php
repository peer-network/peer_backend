<?php

declare(strict_types=1);

namespace Tests\Mocks\Database;

use Fawaz\App\Profile;
use Fawaz\App\Tokenize;
use Fawaz\App\User;
use Fawaz\App\UserInfo;
use Fawaz\Database\UserMapperInterface;

final class MockUserMapper implements UserMapperInterface
{
    private array $users = [];
    private array $userInfos = [];
    private array $referralInfo = [];
    private array $referralLinks = [];
    private array $accessTokens = [];
    private array $refreshTokens = [];
    private array $passwordResetRequests = [];
    private array $resetAttempts = [];
    private array $friends = [];
    private array $followers = [];
    private array $following = [];
    private array $inviterMap = [];
    public array $sentEmails = [];

    public function __construct(array $seedUsers = [])
    {
        foreach ($seedUsers as $userData) {
            $user = $userData instanceof User ? $userData : new User((array) $userData, [], false);
            $this->storeUser($user);
        }
    }

    public function isSameUser(string $userid, string $currentUserId): bool
    {
        return $userid === $currentUserId;
    }

    public function logLoginData(string $userId, ?string $actionType = 'login'): void
    {
        // noop in mock
    }

    public function logLoginDaten(string $userId, ?string $actionType = 'login'): void
    {
        // noop in mock
    }

    public function fetchAll(string $currentUserId, array $args = [], array $specifications = []): array
    {
        return array_values($this->users);
    }

    public function fetchAllAdvance(array $args, array $specifications, ?string $currentUserId = null): array
    {
        return $this->fetchAll($currentUserId ?? '', $args, $specifications);
    }

    public function loadByName(string $username): array
    {
        foreach ($this->users as $user) {
            if (($user['username'] ?? null) === $username) {
                return $user;
            }
        }

        return [];
    }

    public function loadByIdMAin(string $id, int $roles_mask = 0): User|false
    {
        return $this->loadById($id);
    }

    public function loadById(string $id): User|false
    {
        if (!isset($this->users[$id])) {
            return false;
        }

        return new User($this->users[$id], [], false);
    }

    public function loadByEmail(string $email): User|false
    {
        foreach ($this->users as $data) {
            if (($data['email'] ?? null) === $email) {
                return new User($data, [], false);
            }
        }

        return false;
    }

    public function loadUserInfoById(string $id): array|false
    {
        return $this->userInfos[$id] ?? false;
    }

    public function isUserExistById(string $id): bool
    {
        return isset($this->users[$id]);
    }

    public function isEmailTaken(string $email): bool
    {
        foreach ($this->users as $user) {
            if (($user['email'] ?? null) === $email) {
                return true;
            }
        }

        return false;
    }

    public function checkIfNameAndSlugExist(string $username, int $slug): bool
    {
        foreach ($this->users as $user) {
            if (($user['username'] ?? null) === $username && (int)($user['slug'] ?? 0) === $slug) {
                return true;
            }
        }

        return false;
    }

    public function getUserByNameAndSlug(string $username, int $slug): User|bool
    {
        foreach ($this->users as $user) {
            if (($user['username'] ?? null) === $username && (int)($user['slug'] ?? 0) === $slug) {
                return new User($user, [], false);
            }
        }

        return false;
    }

    public function verifyAccount(string $uid): bool
    {
        if (!isset($this->users[$uid])) {
            return false;
        }

        $this->users[$uid]['verified'] = 1;
        return true;
    }

    public function deactivateAccount(string $uid): bool
    {
        if (!isset($this->users[$uid])) {
            return false;
        }

        $this->users[$uid]['status'] = 0;
        return true;
    }

    public function fetchFriends(string $userId, array $specifications, int $offset = 0, int $limit = 10): array
    {
        return $this->friends[$userId] ?? [];
    }

    public function fetchFollowers(string $userId, string $currentUserId, array $specifications, int $offset = 0, int $limit = 10): array
    {
        return $this->followers[$userId] ?? [];
    }

    public function fetchFollowing(string $userId, string $currentUserId, array $specifications, int $offset = 0, int $limit = 10): array
    {
        return $this->following[$userId] ?? [];
    }

    public function createUser(User $userData): ?string
    {
        $this->storeUser($userData);

        return $userData->getArrayCopy()['uid'] ?? null;
    }

    public function insert(User $user): User
    {
        $this->storeUser($user);
        return $user;
    }

    public function insertReferralInfo(string $userId, string $link): void
    {
        $this->referralInfo[$userId] = ['link' => $link];
        $this->referralLinks[$link] = $userId;
    }

    public function getReferralInfoByUserId(string $userId): ?array
    {
        return $this->referralInfo[$userId] ?? null;
    }

    public function getInviterByInvitee(string $userId, array $specs): ?Profile
    {
        $inviterId = $this->inviterMap[$userId] ?? null;
        if ($inviterId === null || !isset($this->users[$inviterId])) {
            return null;
        }

        return new Profile($this->users[$inviterId], [], false);
    }

    public function getReferralRelations(string $userId, array $specs, int $offset = 0, int $limit = 20): array
    {
        $invitees = [];
        foreach ($this->inviterMap as $inviteeId => $inviterId) {
            if ($inviterId === $userId) {
                $invitees[] = $inviteeId;
            }
        }

        return [
            'inviter' => $this->inviterMap[$userId] ?? null,
            'invitees' => $invitees,
        ];
    }

    public function generateReferralLink(string $referralUuid): string
    {
        return $this->referralInfo[$referralUuid]['link'] ?? 'mock-link-' . $referralUuid;
    }

    public function insertinfo(UserInfo $user): UserInfo
    {
        $data = $user->getArrayCopy();
        $this->userInfos[$data['userid'] ?? $data['uid'] ?? ''] = $data;
        return $user;
    }

    public function update(User $user): User
    {
        $this->storeUser($user);
        return $user;
    }

    public function updatePass(User $user): User
    {
        return $this->update($user);
    }

    public function updateProfil(User $user): User
    {
        return $this->update($user);
    }

    public function delete(string $id): bool
    {
        if (!isset($this->users[$id])) {
            return false;
        }

        unset($this->users[$id]);
        return true;
    }

    public function deleteUnverifiedUsers(): void
    {
        foreach ($this->users as $id => $data) {
            if (($data['verified'] ?? 0) === 0) {
                unset($this->users[$id]);
            }
        }
    }

    public function saveOrUpdateAccessToken(string $userid, string $accessToken): void
    {
        $this->accessTokens[$userid] = $accessToken;
    }

    public function saveOrUpdateRefreshToken(string $userid, string $refreshToken): void
    {
        $this->refreshTokens[$userid] = $refreshToken;
    }

    public function deleteAccessTokensByUserId(string $userId): void
    {
        unset($this->accessTokens[$userId]);
    }

    public function deleteRefreshTokensByUserId(string $userId): void
    {
        unset($this->refreshTokens[$userId]);
    }

    public function refreshTokenValidForUser(string $userId, string $refreshToken): bool
    {
        return ($this->refreshTokens[$userId] ?? null) === $refreshToken;
    }

    public function fetchAllFriends(int $offset = 0, int $limit = 20): array
    {
        return array_values($this->friends);
    }

    public function sendPasswordResetEmail(string $email, array $data): void
    {
        $this->sentEmails[] = ['email' => $email, 'data' => $data];
    }

    public function createResetRequest(string $userId, string $token, string $updatedAt, string $expiresAt): array
    {
        $payload = [
            'userid' => $userId,
            'token' => $token,
            'updatedat' => $updatedAt,
            'expiresat' => $expiresAt,
        ];
        $this->passwordResetRequests[$token] = $payload;

        return $payload;
    }

    public function updateAttempt(array $attempt): bool
    {
        if (!isset($attempt['userid'])) {
            return false;
        }

        $this->resetAttempts[$attempt['userid']] = $attempt;
        return true;
    }

    public function checkForPasswordResetExpiry(string $userId): array|bool
    {
        foreach ($this->passwordResetRequests as $payload) {
            if ($payload['userid'] === $userId) {
                return $payload;
            }
        }

        return false;
    }

    public function accessTokenValidForUser(string $userId, string $accessToken): bool
    {
        return ($this->accessTokens[$userId] ?? null) === $accessToken;
    }

    public function isFirstAttemptTooSoon(array $attempt): bool
    {
        $last = $this->resetAttempts[$attempt['userid'] ?? ''] ?? null;
        if ($last === null || !isset($last['attempt'])) {
            return false;
        }

        return ($attempt['timestamp'] ?? 0) - ($last['timestamp'] ?? 0) < 60;
    }

    public function isSecondAttemptTooSoon(array $attempt): bool
    {
        return $this->isFirstAttemptTooSoon($attempt);
    }

    public function rateLimitResponse(int $waitSeconds, ?string $lastAttempt = null): array
    {
        return [
            'status' => 'rate_limited',
            'wait' => $waitSeconds,
            'lastAttempt' => $lastAttempt,
        ];
    }

    public function tooManyAttemptsResponse(): array
    {
        return ['status' => 'error', 'message' => 'Too many attempts'];
    }

    public function getPasswordResetRequest(string $token): ?array
    {
        return $this->passwordResetRequests[$token] ?? null;
    }

    public function deletePasswordResetToken(string $token): void
    {
        unset($this->passwordResetRequests[$token]);
    }

    public function insertoken(Tokenize $data): Tokenize
    {
        $payload = $data->getArrayCopy();
        $this->passwordResetRequests[$payload['token']] = $payload;
        return $data;
    }

    public function getValidReferralInfoByLink(string $referralLink, array $specifications): ?array
    {
        $userId = $this->referralLinks[$referralLink] ?? null;
        return $userId ? $this->referralInfo[$userId] : null;
    }

    public function getInviterID(string $userId): ?string
    {
        return $this->inviterMap[$userId] ?? null;
    }

    public function seedFriends(string $userId, array $friendIds): void
    {
        $this->friends[$userId] = $friendIds;
    }

    public function seedFollowers(string $userId, array $followerIds): void
    {
        $this->followers[$userId] = $followerIds;
    }

    public function seedFollowing(string $userId, array $followingIds): void
    {
        $this->following[$userId] = $followingIds;
    }

    public function assignInviter(string $userId, ?string $inviterId): void
    {
        if ($inviterId === null) {
            unset($this->inviterMap[$userId]);
            return;
        }

        $this->inviterMap[$userId] = $inviterId;
    }

    private function storeUser(User $user): void
    {
        $data = $user->getArrayCopy();
        $this->users[$data['uid']] = $data;
    }
}
