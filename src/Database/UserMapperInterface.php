<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\App\Profile;
use Fawaz\App\Tokenize;
use Fawaz\App\User;
use Fawaz\App\UserInfo;

interface UserMapperInterface
{
    public function isSameUser(string $userid, string $currentUserId): bool;

    public function logLoginData(string $userId, ?string $actionType = 'login'): void;

    public function logLoginDaten(string $userId, ?string $actionType = 'login'): void;

    public function fetchAll(string $currentUserId, array $args = [], array $specifications = []): array;

    public function fetchAllAdvance(array $args, array $specifications, ?string $currentUserId = null): array;

    public function loadByName(string $username): array;

    public function loadByIdMAin(string $id, int $roles_mask = 0): User|false;

    public function loadById(string $id, array $specifications = []): User|false;

    public function loadByEmail(string $email): User|false;

    public function loadUserInfoById(string $id): array|false;

    public function isUserExistById(string $id): bool;

    public function isEmailTaken(string $email): bool;

    public function checkIfNameAndSlugExist(string $username, int $slug): bool;

    public function getUserByNameAndSlug(string $username, int $slug): User|bool;

    public function verifyAccount(string $uid): bool;

    public function deactivateAccount(string $uid): bool;

    public function fetchFriends(string $userId, array $specifications, int $offset = 0, int $limit = 10): ?array;

    public function fetchFollowers(string $userId, string $currentUserId, array $specifications, int $offset = 0, int $limit = 10): array;

    public function fetchFollowing(string $userId, string $currentUserId, array $specifications, int $offset = 0, int $limit = 10): array;

    public function createUser(User $userData): ?string;

    public function insert(User $user): User;

    public function insertReferralInfo(string $userId, string $link): void;

    public function getReferralInfoByUserId(string $userId): ?array;

    public function getInviterByInvitee(string $userId, array $specs): ?Profile;

    public function getReferralRelations(string $userId, array $specs, int $offset = 0, int $limit = 20): ?array;

    public function generateReferralLink(string $referralUuid): string;

    public function insertinfo(UserInfo $user): UserInfo;

    public function update(User $user): User;

    public function updatePass(User $user): User;

    public function updateProfil(User $user): User;

    public function delete(string $id): bool;

    public function deleteUnverifiedUsers(): void;

    public function saveOrUpdateAccessToken(string $userid, string $accessToken): void;

    public function saveOrUpdateRefreshToken(string $userid, string $refreshToken): void;

    public function deleteAccessTokensByUserId(string $userId): void;

    public function deleteRefreshTokensByUserId(string $userId): void;

    public function refreshTokenValidForUser(string $userId, string $refreshToken): bool;

    public function fetchAllFriends(int $offset = 0, int $limit = 20): ?array;

    public function sendPasswordResetEmail(string $email, array $data): void;

    public function createResetRequest(string $userId, string $token, string $updatedAt, string $expiresAt): array;

    public function updateAttempt(array $attempt): bool;

    public function checkForPasswordResetExpiry(string $userId): array|bool;

    public function accessTokenValidForUser(string $userId, string $accessToken): bool;

    public function isFirstAttemptTooSoon(array $attempt): bool;

    public function isSecondAttemptTooSoon(array $attempt): bool;

    public function rateLimitResponse(int $waitSeconds, ?string $lastAttempt = null): array;

    public function tooManyAttemptsResponse(): array;

    public function getPasswordResetRequest(string $token): ?array;

    public function deletePasswordResetToken(string $token): void;

    public function insertoken(Tokenize $data): ?Tokenize;

    public function getValidReferralInfoByLink(string $referralLink, array $specifications): ?array;

    public function getInviterID(string $userId): ?string;
}
