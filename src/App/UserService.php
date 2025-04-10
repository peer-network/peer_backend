<?php

namespace Fawaz\App;

use Fawaz\Database\DailyFreeMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Database\PostMapper;
use Fawaz\Database\WalletMapper;
use Fawaz\Services\Base64FileHandler;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;

class UserService
{
    use ResponseHelper;
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

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    private function validatePassword(string $password): array
    {
        if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password)) {
            return self::respondWithError(
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
        } catch (\Throwable $e) {
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

    public function createUser(array $args): array
    {
        $this->logger->info('UserService.createUser started');

        $requiredFields = ['username', 'email', 'password'];
        $validationErrors = self::validateRequiredFields($args, $requiredFields);
        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $id = self::generateUUID();
        if (empty($id)) {
            $this->logger->critical('Failed to generate user ID');
            return $this->respondWithError('Failed to generate user ID.');
        }

        $username = trim($args['username']);
        $email = trim($args['email']);
        $password = $args['password'];
        $pkey = $args['pkey'] ?? null;
        $mediaFile = isset($args['img']) ? trim($args['img']) : '';
        $isPrivate = (int)($args['isprivate'] ?? 0);
        $invited = $args['invited'] ?? null;

        $biography = $args['biography'] ?? '/userData/' . $id . '.txt';
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($this->userMapper->isEmailTaken($email)) {
            return self::respondWithError(20601);
        }

        $slug = $this->generateUniqueSlug($username);
        $createdat = (new \DateTime())->format('Y-m-d H:i:s.u');

        if ($mediaFile !== '') {
            $args['img'] = $this->uploadMedia($mediaFile, $id, 'profile');
        }

        $userData = [
            'uid' => $id,
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'status' => 0,
            'verified' => 0,
            'slug' => $slug,
            'roles_mask' => 0,
            'ip' => $ip,
            'img' => $args['img'] ?? '/profile/' . $id . '.jpg',
            'biography' => $biography,
            'createdat' => $createdat,
            'updatedat' => $createdat
        ];

        $infoData = [
            'userid' => $id,
            'liquidity' => 0.0,
            'amountposts' => 0,
            'amountblocked' => 0,
            'amountfollower' => 0,
            'amountfollowed' => 0,
            'amountfriends' => 0,
            'isprivate' => $isPrivate,
            'invited' => $invited,
            'pkey' => $pkey,
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
            $user = new User($userData);
            $this->userMapper->createUser($user);
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
        } catch (\Throwable $e) {
            $this->logger->warning('Error registering user.', ['exception' => $e]);
            return self::respondWithError($e->getMessage());
        }

		return [
			'status' => 'success',
			'ResponseCode' => 10601,
			'userid' => $id,
		];
    }

    private function uploadMedia(string $mediaFile, string $userId, string $folder): ?string
    {
        try {


            if (!empty($mediaFile)) {
                $mediaPath = $this->base64filehandler->handleFileUpload($mediaFile, 'image', $userId, $folder);
                $this->logger->info('UserService.uploadMedia mediaPath', ['mediaPath' => $mediaPath]);

                if ($mediaPath === '') {
                    return self::respondWithError('Media upload failed');
                }

                if (isset($mediaPath['path'])) {
                    return $mediaPath['path'];
                } else {
                    return self::respondWithError(30305);
                }

            } else {
                return self::respondWithError('Media necessary for upload');
            }


        } catch (\Throwable $e) {
            $this->logger->error('Error uploading media.', ['exception' => $e]);
        }

        return null;
    }

    public function verifyAccount(string $userId): array
    {
        if (!self::isValidUUID($userId)) {
            return self::respondWithError('Could not find mandatory id.');
        }

        try {
            return $this->userMapper->verifyAccount($userId);
        } catch (\Throwable $e) {
            $this->logger->error('Error verifying account.', ['exception' => $e]);
            return self::respondWithError(40701);
        }
    }

    public function deleteUnverifiedUsers(): bool
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        try {
            $this->userMapper->deleteUnverifiedUsers();
            $this->logger->info('Unverified users deleted.');
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Error deleting unverified users.', ['exception' => $e]);
            return false;
        }
    }

    public function setPassword(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        if (empty($args)) {
            return self::respondWithError('Could not find mandatory args');
        }

        $this->logger->info('UserService.setPassword started');

        $newPassword = $args['password'] ?? null;
        $currentPassword = $args['expassword'] ?? null;

        $passwordValidation = $this->validatePassword($newPassword);
        if ($passwordValidation['status'] === 'error') {
            return $passwordValidation;
        }

        if ($newPassword === $currentPassword) {
            return self::respondWithError(21002);
        }

        $user = $this->userMapper->loadById($this->currentUserId);

        if (!$user) {
            $this->logger->warning('User not found', ['userId' => $this->currentUserId]);
            return self::respondWithError('User not found');
        }

        if (!$this->validatePasswordMatch($currentPassword, $user->getPassword())) {
            return self::respondWithError(31001);
        }

        try {
            $user->validatePass($args);
            $this->userMapper->updatePass($user);

            $this->logger->info('User password updated successfully', ['userId' => $this->currentUserId]);
            return [
                'status' => 'success',
                'ResponseCode' => 11005,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update user password', ['exception' => $e]);
            return self::respondWithError(41004);
        }
    }

    public function setEmail(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        if (empty($args)) {
            return self::respondWithError('Could not find mandatory args');
        }

        $this->logger->info('UserService.setEmail started');

        $email = $args['email'] ?? null;
        $exPassword = $args['password'] ?? null;

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Invalid email format', ['email' => $email]);
            return self::respondWithError('Invalid email format');
        }

        if ($this->userMapper->isEmailTaken($email)) {
            $this->logger->warning('Email already in use', ['email' => $email]);
            return self::respondWithError(21003);
        }

        $user = $this->userMapper->loadById($this->currentUserId);
        if (!$user) {
            $this->logger->warning('User not found', ['userId' => $this->currentUserId]);
            return self::respondWithError('User not found');
        }

        if ($email === $user->getMail()) {
            return self::respondWithError(21004);
        }

        if (!$this->validatePasswordMatch($exPassword, $user->getPassword())) {
            return self::respondWithError(31001);
        }

        try {
            $user->setMail($email);
            $data = $this->userMapper->updateProfil($user);
            $affectedRows = $data->getArrayCopy();

            $this->logger->info('User email updated successfully', ['userId' => $this->currentUserId, 'email' => $email]);
            return [
                'status' => 'success',
                'ResponseCode' => 11006,
                'affectedRows' => $affectedRows,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update user email', ['exception' => $e]);
            return self::respondWithError(41005);
        }
    }

    public function setUsername(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        if (empty($args['username'])) {
            return self::respondWithError('Could not find mandatory args');
        }

        $this->logger->info('UserService.setUsername started');

        $username = trim($args['username'] ?? '');
        $password = $args['password'] ?? null;

        try {
            $validationResult = new User(['username' => $username], ['username']);

            $user = $this->userMapper->loadById($this->currentUserId);
            if (!$user) {
                return self::respondWithError(21001);
            }

            if ($username === $user->getName()) {
                return self::respondWithError(21005);
            }

            if (!$this->validatePasswordMatch($password, $user->getPassword())) {
                return self::respondWithError(31001);
            }

            $slug = $this->generateUniqueSlug($username);
            if (!$slug) {
                return self::respondWithError('Failed to generate a unique slug after multiple attempts.');
            }

            $user->setName($username);
            $user->setSlug($slug);

            $data = $this->userMapper->updateProfil($user);
            $affectedRows = $data->getArrayCopy();

            $this->logger->info('Username updated successfully', ['id' => $this->currentUserId, 'username' => $username, 'slug' => $slug]);

            return [
                'status' => 'success',
                'ResponseCode' => 11007,
                'affectedRows' => $affectedRows,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update username', ['exception' => $e]);
            return self::respondWithError(41006);
        }
    }

    public function deleteAccount(string $expassword): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        if (empty($expassword)) {
            return self::respondWithError('Could not find mandatory expassword');
        }

        $this->logger->info('UserService.deleteAccount started');

        $userId = $this->currentUserId;

        $user = $this->userMapper->loadById($userId);
        if (!$user) {
            return self::respondWithError('Could not find user');
        }

        if (!$this->validatePasswordMatch($expassword, $user->getPassword())) {
            return self::respondWithError(31001);
        }

        try {
            $this->userMapper->delete($userId);
            $this->logger->info('User deleted successfully', ['userId' => $userId]);
            return [
                'status' => 'success',
                'message' => 'User deleted successfully',
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete user', ['exception' => $e]);
            return self::respondWithError('Failed to delete user');
        }
    }

    public function Profile(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        $userId = $args['userid'] ?? $this->currentUserId;
        $postLimit = min(max((int)($args['postLimit'] ?? 4), 1), 10);

        $this->logger->info('UserService.Profile started');

        if (!self::isValidUUID($userId)) {
            $this->logger->warning('Invalid UUID for profile', ['userId' => $userId]);
            return self::respondWithError(30102);
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
                'ResponseCode' => 11008,
                'affectedRows' => $profileData,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch profile data', [
                'userId' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return self::respondWithError(41007);
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
            return self::respondWithError('Invalid UUID provided for Follows.');
        }

        try {
            $followers = $this->userMapper->fetchFollowers($userId, $this->currentUserId, $offset, $limit);
            $following = $this->userMapper->fetchFollowing($userId, $this->currentUserId, $offset, $limit);
            
            $counter = count($followers) + count($following);

            return [
                'status' => 'success',
                'counter' => $counter,
                'ResponseCode' => 11101,
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
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch followers or following data', ['error' => $e->getMessage()]);
            return self::respondWithError(41104);
        }
    }

    public function getFriends(?array $args = []): array|null
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
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
                    'ResponseCode' => 11102,
                    'affectedRows' => $users,
                ];
            }

            $this->logger->info('No friends found for the user', ['currentUserId' => $this->currentUserId]);
            return self::respondWithError('No friends found for the user.');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch friends', ['exception' => $e->getMessage()]);
            return self::respondWithError('Failed to retrieve friends list.');
        }
    }

    public function getAllFriends(?array $args = []): array|null
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
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
            return self::respondWithError('No friends found @ all.');
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch friends', ['exception' => $e->getMessage()]);
            return self::respondWithError('Failed to retrieve friends list.');
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
                    'ResponseCode' => 11009,
                    'affectedRows' => $fetchAll,
                ];
            }

            return self::respondWithError('No users found for the user.');
        } catch (\Throwable $e) {
            return self::respondWithError('Failed to retrieve users list.');
        }
    }

    public function fetchAll(?array $args = []): array
    {

        $this->logger->info('UserService.fetchAll started');

        try {
            $users = $this->userMapper->fetchAll($args);
            $fetchAll = array_map(fn(User $user) => $user->getArrayCopy(), $users);

            if ($fetchAll) {
                return [
                    'status' => 'success',
                    'counter' => count($fetchAll),
                    'ResponseCode' => 11009,
                    'affectedRows' => $fetchAll,
                ];
            }

            return self::respondWithError('No users found for the user.');
        } catch (\Throwable $e) {
            return self::respondWithError('Failed to retrieve users list.');
        }
    }
}
