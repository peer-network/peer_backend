<?php

namespace Fawaz\App;

use Fawaz\Database\DailyFreeMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Database\PostMapper;
use Fawaz\Database\WalletMapper;
use Fawaz\Services\Base64FileHandler;
use Psr\Log\LoggerInterface;

class UserService
{
    protected ?string $currentUserId = null;
    private Base64FileHandler $base64filehandler;

    public function __construct(
        protected LoggerInterface $logger,
        protected DailyFreeMapper $dailyFreeMapper,
        protected UserMapper $userMapper,
        protected PostMapper $postMapper,
        protected WalletMapper $walletMapper
    ) {
        $this->base64filehandler = new Base64FileHandler();
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function generateUUID(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0x0fff) | 0x4000,
            \mt_rand(0, 0x3fff) | 0x8000,
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff), \mt_rand(0, 0xffff)
        );
    }

    private static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    private function validateUsername(string $username): array
    {
        if ($username === '') {
            return $this->respondWithError('Could not find mandatory username');
        }

        if (strlen($username) < 3 || strlen($username) > 23) {
            return $this->respondWithError('Username must be between 3 and 23 characters.');
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return $this->respondWithError('Username must only contain letters, numbers, and underscores.');
        }

        return ['status' => 'success'];
    }

    private function validatePassword(string $password): array
    {
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            return $this->respondWithError(
                'Password must be at least 8 characters long and contain at least one lowercase letter, one uppercase letter, and one number.'
            );
        }

        return ['status' => 'success'];
    }

    private function validatePasswordMatch(?string $inputPassword, string $hashedPassword): bool
    {
        if (empty($inputPassword) || empty($hashedPassword)) {
            $this->logger->warning('Password or hash cannot be empty');
            return false;
        }

        try {
            return password_verify($inputPassword, $hashedPassword);
        } catch (\Exception $e) {
            $this->logger->error('Password verification error', ['exception' => $e]);
            return false;
        }
    }

    private function createNumbersAsString(int $start, int $end, int $count): string
    {
        $numbers = range($start, $end);
        shuffle($numbers);
        return implode('', array_slice($numbers, 0, $count));
    }

    private function generateUniqueSlug(string $username, int $maxRetries = 5): ?string
    {
        $attempts = 0;
        do {
            $slug = $this->createNumbersAsString(1, 9, 5);
            if (!$this->userMapper->checkIfNameAndSlugExist($username, $slug)) {
                return (string) $slug;
            }
            $attempts++;
        } while ($attempts < $maxRetries);

        $this->logger->error('Failed to generate unique slug after maximum retries', ['username' => $username]);
        return null;
    }

    private function validateRequiredFields(array $args, array $requiredFields): array
    {
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                return $this->respondWithError("$field is required");
            }
        }
        return [];
    }

    public function createUser(array $args): array
    {
        $this->logger->info('UserService.createUser started');

        $requiredFields = ['username', 'email', 'password'];
        $validationErrors = $this->validateRequiredFields($args, $requiredFields);
        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $username = trim($args['username']);
        $email = trim($args['email']);
        $password = $args['password'];
        $mediaFile = isset($args['img']) ? trim($args['img']) : '';
        $isPrivate = (int)($args['isprivate'] ?? 0);
        $invited = $args['invited'] ?? null;
        $id = $this->generateUUID();
        $biography = $args['biography'] ?? '/userData/' . $id . '.txt';

        $usernameValidation = $this->validateUsername($username);
        if ($usernameValidation['status'] === 'error') {
            return $usernameValidation;
        }

        $passwordValidation = $this->validatePassword($password);
        if ($passwordValidation['status'] === 'error') {
            return $passwordValidation;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->respondWithError('Invalid email format.');
        }

        if ($this->userMapper->isEmailTaken($email)) {
            return $this->respondWithError('Email already registered.');
        }

        $slug = $this->generateUniqueSlug($username);
        $createdat = (new \DateTime())->format('Y-m-d H:i:s.u');

        if ($mediaFile !== '') {
            $args['img'] = $this->uploadMedia($mediaFile, $id, 'profile');
        }

        $args = [
            'uid' => $id,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'biography' => $biography,
            'isprivate' => $isPrivate,
            'slug' => $slug,
            'img' => $args['img'] ?? '/profile/' . $id . '.jpg',
        ];

        $infoData = [
            'userid' => $id,
            'liquidity' => 0.0,
            'amountposts' => 0,
            'amountblocked' => 0,
            'amountfollower' => 0,
            'amountfollowed' => 0,
            'isprivate' => 0,
            'invited' => $invited,
            'updatedat' => $createdat,
        ];

        $walletData = [
            'userid' => $id,
            'liquidity' => 0.0,
            'liquiditq' => 0,
            'updatedat' => $createdat,
            'createdat' => $createdat,
        ];

        $dailyData = [
            'userid' => $id,
            'liken' => 0,
            'comments' => 0,
            'posten' => 0,
            'createdat' => $createdat
        ];

        try {
            $this->userMapper->createUser($args);
            unset($args);

            $userinfo = new UserInfo($infoData);

            $this->userMapper->insertinfo($userinfo);
            unset($infoData, $userinfo);

            $userwallet = new Wallett($walletData);

            $this->walletMapper->insertt($userwallet);
            unset($walletData, $userwallet);

            $createuserDaily = new DailyFree($dailyData);

            $this->dailyFreeMapper->insert($createuserDaily);
            unset($dailyData, $createuserDaily);

            $this->userMapper->logLoginDaten($id);
            $this->logger->info('User registered successfully.', ['username' => $username, 'email' => $email]);
            return [
                'status' => 'success',
                'ResponseCode' => 'User registered successfully. Please verify your account.',
                'userid' => $id,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error registering user.', ['exception' => $e]);
            return $this->respondWithError('Failed to register user.');
        }
    }

    private function uploadMedia(string $mediaFile, string $userId, string $folder): ?string
    {
        try {


            if (!empty($mediaFile)) {
                $mediaPath = $this->base64filehandler->handleFileUpload($mediaFile, 'image', $userId, $folder);
                $this->logger->info('UserService.uploadMedia mediaPath', ['mediaPath' => $mediaPath]);

                if ($mediaPath === '') {
                    return $this->respondWithError('Media upload failed');
                }

                if (isset($mediaPath['path'])) {
                    return $mediaPath['path'];
                } else {
                    return $this->respondWithError('Media path necessary for upload');
                }

            } else {
                return $this->respondWithError('Media necessary for upload');
            }


        } catch (\Exception $e) {
            $this->logger->error('Error uploading media.', ['exception' => $e]);
        }

        return null;
    }

    public function searchUsername(string $username): array|object
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized.');
        }

        $this->logger->info('searchUsername started.');

        $validationResult = $this->validateUsername($username);
        if ($validationResult['status'] === 'error') {
            return $validationResult;
        }

        try {
            $user = $this->userMapper->loadByName($username);
            if (!$user) {
                return $this->respondWithError('Username not found.');
            }
            return $user;
        } catch (\Exception $e) {
            $this->logger->error('Error searching username.', ['exception' => $e]);
            return $this->respondWithError('Failed to search username.');
        }
    }

    public function verifyAccount(string $userId): array
    {
        if (!self::isValidUUID($userId)) {
            return $this->respondWithError('Could not find mandatory id.');
        }

        try {
            return $this->userMapper->verifyAccount($userId);
        } catch (\Exception $e) {
            $this->logger->error('Error verifying account.', ['exception' => $e]);
            return $this->respondWithError('Failed to verify account.');
        }
    }

    public function deleteUnverifiedUsers(): bool
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized.');
        }

        try {
            $this->userMapper->deleteUnverifiedUsers();
            $this->logger->info('Unverified users deleted.');
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error deleting unverified users.', ['exception' => $e]);
            return false;
        }
    }

    public function setPassword(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (empty($args)) {
            return $this->respondWithError('Could not find mandatory args');
        }

        $this->logger->info('UserService.setPassword started');

        $newPassword = $args['password'] ?? null;
        $currentPassword = $args['expassword'] ?? null;

        $passwordValidation = $this->validatePassword($newPassword);
        if ($passwordValidation['status'] === 'error') {
            return $passwordValidation;
        }

        if ($newPassword === $currentPassword) {
            return $this->respondWithError('New password cannot be the same as the current password.');
        }

        $user = $this->userMapper->loadById($this->currentUserId);

        if (!$user) {
            $this->logger->warning('User not found', ['userId' => $this->currentUserId]);
            return $this->respondWithError('User not found');
        }

        if (!$this->validatePasswordMatch($currentPassword, $user->getPassword())) {
            return $this->respondWithError('Wrong Actual Password');
        }

        try {
            $user->validatePass($args);
            $this->userMapper->updatePass($user);

            $this->logger->info('User password updated successfully', ['userId' => $this->currentUserId]);
            return [
                'status' => 'success',
                'ResponseCode' => 'Password update successful',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to update user password', ['exception' => $e]);
            return $this->respondWithError('Failed to update user password');
        }
    }

    public function setEmail(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (empty($args)) {
            return $this->respondWithError('Could not find mandatory args');
        }

        $this->logger->info('UserService.setEmail started');

        $email = $args['email'] ?? null;
        $exPassword = $args['password'] ?? null;

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Invalid email format', ['email' => $email]);
            return $this->respondWithError('Invalid email format');
        }

        if ($this->userMapper->isEmailTaken($email)) {
            $this->logger->warning('Email already in use', ['email' => $email]);
            return $this->respondWithError('Email already in use.');
        }

        $user = $this->userMapper->loadById($this->currentUserId);
        if (!$user) {
            $this->logger->warning('User not found', ['userId' => $this->currentUserId]);
            return $this->respondWithError('User not found');
        }

        if ($email === $user->getMail()) {
            return $this->respondWithError('New email cannot be the same as the current email.');
        }

        if (!$this->validatePasswordMatch($exPassword, $user->getPassword())) {
            return $this->respondWithError('Wrong Actual Password');
        }

        try {
            $user->setMail($email);
            $data = $this->userMapper->updateProfil($user);
            $affectedRows = $data->getArrayCopy();

            $this->logger->info('User email updated successfully', ['userId' => $this->currentUserId, 'email' => $email]);
            return [
                'status' => 'success',
                'ResponseCode' => 'User email updated successfully',
                'affectedRows' => $affectedRows,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to update user email', ['exception' => $e]);
            return $this->respondWithError('Could not update user’s email');
        }
    }

    public function setUsername(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (empty($args)) {
            return $this->respondWithError('Could not find mandatory args');
        }

        $this->logger->info('UserService.setUsername started');

        $username = trim($args['username'] ?? '');
        $password = $args['password'] ?? null;

        $validationResult = $this->validateUsername($username);
        if ($validationResult['status'] === 'error') {
            return $validationResult;
        }

        $user = $this->userMapper->loadById($this->currentUserId);
        if (!$user) {
            return $this->respondWithError('User not found');
        }

        if ($username === $user->getName()) {
            return $this->respondWithError('New username cannot be the same as the current username.');
        }

        if (!$this->validatePasswordMatch($password, $user->getPassword())) {
            return $this->respondWithError('Wrong Actual Password');
        }

        $slug = $this->generateUniqueSlug($username);
        if (!$slug) {
            return $this->respondWithError('Failed to generate a unique slug after multiple attempts.');
        }

        try {
            $user->setName($username);
            $user->setSlug($slug);

            $data = $this->userMapper->updateProfil($user);
            $affectedRows = $data->getArrayCopy();

            $this->logger->info('Username updated successfully', ['id' => $this->currentUserId, 'username' => $username, 'slug' => $slug]);

            return [
                'status' => 'success',
                'ResponseCode' => 'Username updated successfully',
                'affectedRows' => $affectedRows,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to update username', ['exception' => $e]);
            return $this->respondWithError('Could not update user’s username');
        }
    }

    public function deleteAccount(string $expassword): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (empty($expassword)) {
            return $this->respondWithError('Could not find mandatory expassword');
        }

        $this->logger->info('UserService.deleteAccount started');

        $userId = $this->currentUserId;

        $user = $this->userMapper->loadById($userId);
        if (!$user) {
            return $this->respondWithError('Could not find user');
        }

        if (!$this->validatePasswordMatch($expassword, $user->getPassword())) {
            return $this->respondWithError('Wrong Actual Password');
        }

        try {
            $this->userMapper->delete($userId);
            $this->logger->info('User deleted successfully', ['userId' => $userId]);
            return [
                'status' => 'success',
                'message' => 'User deleted successfully',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete user', ['exception' => $e]);
            return $this->respondWithError('Failed to delete user');
        }
    }

    public function Profile(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $userId = $args['userid'] ?? $this->currentUserId;
        $postLimit = min(max((int)($args['postLimit'] ?? 4), 1), 10);

        $this->logger->info('UserService.Profile started');

        if (!self::isValidUUID($userId)) {
            $this->logger->warning('Invalid UUID for profile', ['userId' => $userId]);
            return $this->respondWithError('Could not find mandatory id.');
        }

        try {
            $profileData = $this->userMapper->fetchProfileData($userId, $this->currentUserId)->getArrayCopy();
            $this->logger->info("Fetched profile data", ['profileData' => $profileData]);

            $posts = $this->postMapper->fetchPostsByType($userId, $postLimit);

            $contentTypes = ['image', 'video', 'audio', 'text'];
            foreach ($contentTypes as $type) {
                $profileData["{$type}posts"] = array_filter($posts, fn($post) => $post['contenttype'] === $type);
            }

            $this->logger->info('Profile data prepared successfully', ['userId' => $userId]);
            return [
                'status' => 'success',
                'ResponseCode' => 'Profile data prepared successfully',
                'affectedRows' => $profileData,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch profile data', [
                'userId' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return $this->respondWithError('Failed to fetch profile data.');
        }
    }

    public function Follows(?array $args = []): array
    {
        $this->logger->info('UserService.Follows started');

        $userId = $args['userid'] ?? $this->currentUserId;
        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        if (!self::isValidUUID($userId)) {
            $this->logger->warning('Invalid UUID provided for Follows', ['userId' => $userId]);
            return $this->respondWithError('Invalid UUID provided for Follows.');
        }

        try {
            $followers = $this->userMapper->fetchFollowers($userId, $this->currentUserId, $offset, $limit);
            $following = $this->userMapper->fetchFollowing($userId, $this->currentUserId, $offset, $limit);
            
            $counter = count($followers) + count($following);

            return [
                'status' => 'success',
                'counter' => $counter,
                'ResponseCode' => 'Follows data prepared successfully',
                'affectedRows' => [
                    'followers' => array_map(
                        fn(ProfilUser $follower) => $follower->getArrayCopy(),
                        $followers
                    ),
                    'following' => array_map(
                        fn(ProfilUser $followed) => $followed->getArrayCopy(),
                        $following
                    )
                ]
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch followers or following data', ['error' => $e->getMessage()]);
            return $this->respondWithError('Failed to fetch followers or following data.');
        }
    }

    public function FollowRelations(?array $args = []): array
    {
        $this->logger->info('UserService.FollowRelations started');

        $userId = $args['userid'] ?? $this->currentUserId;
        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        if (!self::isValidUUID($userId)) {
            return $this->respondWithError('Invalid UUID provided for Follows.');
        }

        try {
            $followers = $this->userMapper->fetchFollowRelations($userId, $this->currentUserId, $offset, $limit, 'followers');
            $following = $this->userMapper->fetchFollowRelations($userId, $this->currentUserId, $offset, $limit, 'following');
            $friends = $this->userMapper->fetchFollowRelations($userId, $this->currentUserId, $offset, $limit, 'friends');
            $counter = count($followers) + count($following) + count($friends);

            return [
                'status' => 'success',
                'counter' => $counter,
                'ResponseCode' => 'FollowRelations data prepared successfully',
                'affectedRows' => [
                'followers' => array_map(
                    fn(ProfilUser $follower) => $follower->getArrayCopy(),
                    $followers
                ),
                'following' => array_map(
                    fn(ProfilUser $followed) => $followed->getArrayCopy(),
                    $following
                ),
                'friends' => array_map(
                    fn(ProfilUser $followed) => $followed->getArrayCopy(),
                    $friends
                )]
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch follow relations data', ['error' => $e->getMessage()]);
            return $this->respondWithError('Failed to fetch followers or following data.');
        }
    }

    public function getFriends(?array $args = []): array|null
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        $this->logger->info('Fetching friends list', ['currentUserId' => $this->currentUserId, 'offset' => $offset, 'limit' => $limit]);

        try {
            $users = $this->userMapper->fetchFriends($this->currentUserId, $offset, $limit);

            if (!empty($users)) {
                $this->logger->info('Friends list retrieved successfully', ['userCount' => count($users)]);
                return [
                    'status' => 'success',
                    'counter' => count($users),
                    'ResponseCode' => 'Friends data prepared successfully',
                    'affectedRows' => $users,
                ];
            }

            $this->logger->info('No friends found for the user', ['currentUserId' => $this->currentUserId]);
            return $this->respondWithError('No friends found for the user.');
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch friends', ['exception' => $e->getMessage()]);
            return $this->respondWithError('Failed to retrieve friends list.');
        }
    }

    public function getAllFriends(?array $args = []): array|null
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        $this->logger->info('Fetching all friends list', ['offset' => $offset, 'limit' => $limit]);

        try {
            $users = $this->userMapper->fetchAllFriends($offset, $limit);

            if (!empty($users)) {
                $this->logger->info('All friends list retrieved successfully', ['userCount' => count($users)]);
                return [
                    'status' => 'success',
                    'counter' => count($users),
                    'ResponseCode' => 'All friends data prepared successfully',
                    'affectedRows' => $users,
                ];
            }

            $this->logger->info('No friends found @ all');
            return $this->respondWithError('No friends found @ all.');
        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch friends', ['exception' => $e->getMessage()]);
            return $this->respondWithError('Failed to retrieve friends list.');
        }
    }

    public function fetchAllAdvance(?array $args = []): array
    {

        $this->logger->info('UserService.fetchAllAdvance started');

        try {
            $users = $this->userMapper->fetchAllAdvance($args, $this->currentUserId);
            $fetchAll = array_map(fn(UserAdvanced $user) => $user->getArrayCopy(), $users);

            if ($fetchAll) {
                return [
                    'status' => 'success',
                    'counter' => count($fetchAll),
                    'ResponseCode' => 'Users data prepared successfully',
                    'affectedRows' => $fetchAll,
                ];
            }

            return $this->respondWithError('No users found for the user.');
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to retrieve users list.');
        }
    }

    public function fetchAll(?array $args = []): array
    {

        $this->logger->info('UserService.fetchAll started');

        try {
            $users = $this->userMapper->fetchAll($args, $this->currentUserId);
            $fetchAll = array_map(fn(User $user) => $user->getArrayCopy(), $users);

            if ($fetchAll) {
                return [
                    'status' => 'success',
                    'counter' => count($fetchAll),
                    'ResponseCode' => 'Users data prepared successfully',
                    'affectedRows' => $fetchAll,
                ];
            }

            return $this->respondWithError('No users found for the user.');
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to retrieve users list.');
        }
    }
}
