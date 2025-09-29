<?php

namespace Fawaz\App;

use Fawaz\Database\DailyFreeMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Database\UserPreferencesMapper;
use Fawaz\Database\PostMapper;
use Fawaz\Database\WalletMapper;
use Fawaz\Mail\UserWelcomeMail;
use Fawaz\Services\Base64FileHandler;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Strategies\ListPostsContentFilteringStrategy;
use Fawaz\Services\Mailer;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\App\UserPreferences;

class UserService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;
    private Base64FileHandler $base64filehandler;

    public function __construct(
        protected LoggerInterface $logger,
        protected DailyFreeMapper $dailyFreeMapper,
        protected UserMapper $userMapper,
        protected UserPreferencesMapper $userPreferencesMapper,
        protected PostMapper $postMapper,
        protected WalletMapper $walletMapper,
		protected Mailer $mailer,
        protected TransactionManager $transactionManager
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
        $passwordConfig = ConstantsConfig::user()['PASSWORD'];

        if (strlen($password) < $passwordConfig['MIN_LENGTH'] || strlen($password) > $passwordConfig['MAX_LENGTH']) {
            return self::respondWithError(30226);
        }
        if (!preg_match('/' . $passwordConfig['PATTERN'] . '/u', $password)) {
            return self::respondWithError(30226);
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

    private function generateUniqueSlug(string $username, int $maxRetries = 5): ?int
    {
        $attempts = 0;
        do {
            $slug = $this->createNumbersAsString(1, 9, 5);
            if (!$this->userMapper->checkIfNameAndSlugExist($username, $slug)) {
                return (int) $slug;
            }
            $attempts++;
        } while ($attempts < $maxRetries);

        $this->logger->error('Failed to generate unique slug after maximum retries', ['username' => $username]);
        return null;
    }

    private function createPayload(string $email, string $username, string $verificationCode): array
    {
        $email = trim($email);
        $username = trim($username);
        $verificationCode = trim($verificationCode);

		if (empty($email) || empty($username) || empty($verificationCode)){
			return self::respondWithError(40701);
		}

		$payload = [
			"to" => [
				[
					"email" => $email,
					"name" => $username
				]
			],
			"templateId" => 1,
			"params" => [
				"verification_code" => $verificationCode
			]
		];

        try {
            return $payload;
        } catch (\Throwable $e) {
            $this->logger->error('Error create payload.', ['exception' => $e]);
            return self::respondWithError('Error create payload.');
        }
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
            return $this->respondWithError(40602);
        }

        $pkey = $args['pkey'] ?? null;
        $mediaFile = isset($args['img']) ? trim($args['img']) : '';
        $isPrivate = (int)($args['isprivate'] ?? 0);
        $referralUuid = $args['referralUuid'] ?? null;
        $invited = null;
		$bin2hex = bin2hex(random_bytes(32));
		$expiresat = (int)\time()+1800;

        $biography = $args['biography'] ?? '/userData/' . $id . '.txt';
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!empty($referralUuid)) {
            if(!self::isValidUUID($referralUuid)) {
                $this->logger->warning('Invalid referral UUID format.', ['referralUuid' => $referralUuid]);
                return self::respondWithError(31007);
            }

            $inviter = $this->userMapper->loadById($referralUuid);

            if (empty($inviter)) {
                $this->logger->warning('Invalid referral UUID provided.', ['referralUuid' => $referralUuid]);
                return self::respondWithError(31007);
            }

            $invited = $inviter->getUserId();
        }

        $email = trim($args['email']);
        if ($this->userMapper->isEmailTaken($email)) {
            return self::respondWithError(30601);
        }

        $username = trim($args['username']);
        $slug = $this->generateUniqueSlug($username);
        $createdat = (new \DateTime())->format('Y-m-d H:i:s.u');

        if ($mediaFile !== '') {
            $args['img'] = $this->uploadMedia($mediaFile, $id, 'profile');
        }

        $verificationData = [
            'token' => $bin2hex,
            'userid' => $id,
            'attempt' => 1,
            'expiresat' => $expiresat,
			'updatedat' => (new \DateTime())->format('Y-m-d H:i:s.u')
        ];

		$this->logger->info('UserService.createUser.verificationData started', ['verificationData' => $verificationData]);

        $userData = [
            'uid' => $id,
            'email' => $email,
            'username' => $username,
            'password' => $args['password'],
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

        $userPreferencesSrc = [
            'userid' => $id,
            'contentFilteringSeverityLevel' => null,
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

        $this->transactionManager->beginTransaction();
        try {
            $user = new User($userData);
            $this->userMapper->createUser($user);
            unset($args);
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->warning('Error registering User::User.', ['exception' => $e]);
            return self::respondWithError(40601);
        }

        try {
            $toInsert = new Tokenize($verificationData);
			$this->userMapper->insertoken($toInsert);
            unset($verificationData, $toInsert);
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->warning('Error registering User::Tokenize.', ['exception' => $e]);
            return self::respondWithError(40601);
        }

        try {
            $userinfo = new UserInfo($infoData);
            $this->userMapper->insertinfo($userinfo);
            unset($infoData, $userinfo);
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->warning('Error registering User::UserInfo.', ['exception' => $e]);
            return self::respondWithError(40601);
        }

        try {
            $userPreferences = new UserPreferences($userPreferencesSrc);
            $this->userPreferencesMapper->insert($userPreferences);
            unset($userPreferencesSrc, $userPreferences);
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->warning('Error registering User::UserPreferences.', ['exception' => $e]);
            return self::respondWithError(41016);
        }

        try {
            $referralLink = $this->userMapper->generateReferralLink($id);
            $this->userMapper->insertReferralInfo($id, $referralLink);
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->warning('Error handling referral info.', ['exception' => $e]);
            return self::respondWithError(41013);
        }

        try {
            $userwallet = new Wallett($walletData);
            $this->walletMapper->insertt($userwallet);
            unset($walletData, $userwallet);
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->warning('Error registering User::Wallett.', ['exception' => $e]);
            return self::respondWithError(40601);
        }

        try {
            $createuserDaily = new DailyFree($dailyData);
            $this->dailyFreeMapper->insert($createuserDaily);
            unset($dailyData, $createuserDaily);
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->warning('Error registering User::DailyFree.', ['exception' => $e]);
            return self::respondWithError(40601);
        }

        $this->userMapper->logLoginDaten($id);
        $this->logger->info('User registered successfully.', ['username' => $username, 'email' => $email]);

        try {
            $data = [
                'username' => $username
            ];
            (new UserWelcomeMail($data))->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Error occurred while sending welcome email: ' . $e->getMessage());
        }
        $this->transactionManager->commit();
		return [
			'status' => 'success',
			'ResponseCode' => 10601,
			'userid' => $id,
		];
    }

    public function verifyReferral(string $referralString): array
    {
        if (empty($referralString)) {
            return self::respondWithError(31010); // Invalid referral string
        }

        if (!self::isValidUUID($referralString)) {
            return self::respondWithError(31010);
        }
        try {
            $users = $this->userMapper->getValidReferralInfoByLink($referralString);

            if(!$users){
                return self::respondWithError(31007); // No valid referral information found
            }
            $userObj = (new User($users, [], false))->getArrayCopy();

            return [
                'status' => 'success',
                'ResponseCode' => 11011, // Referral Info retrived
                'affectedRows' => $userObj
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Error verifying referral info.', ['exception' => $e]);
            return self::respondWithError(41013); // Error while retriving Referral Info
        }
        return self::respondWithError(31010); // Error while retriving Referral Info
    }

    public function referralList(string $userId, int $offset = 0, int $limit = 20): array
    {
        $data = $this->userMapper->getReferralRelations($userId, $offset, $limit);

        return [
            'status' => 'success',
            'ResponseCode' => 11011,
            'counter' => count($data['iInvited']),
            'affectedRows' => [
                'invitedBy' => $data['invitedBy'],
                'iInvited' => $data['iInvited']
            ],
        ];
    }
    
    private function uploadMedia(string $mediaFile, string $userId, string $folder): array
    {
        try {

            if (!empty($mediaFile)) {
                $mediaPath = $this->base64filehandler->handleFileUpload($mediaFile, 'image', $userId, $folder);
                $this->logger->info('UserService.uploadMedia mediaPath', ['mediaPath' => $mediaPath]);

                if (empty($mediaPath)) {
                    return self::respondWithError(30251);
                }

                if (isset($mediaPath['path'])) {
                    return $mediaPath['path'];
                } else {
                    return self::respondWithError(40306);
                }

            } else {
                return self::respondWithError(40307);
            }


        } catch (\Throwable $e) {
            $this->logger->error('Error uploading media.', ['exception' => $e]);
        }

        return self::respondWithError(40307);
    }

    public function verifyAccount(string $userId): array
    {
        if (!self::isValidUUID($userId)) {
            return self::respondWithError(30201);
        }

        try {
            $this->transactionManager->beginTransaction();
            $success = $this->userMapper->verifyAccount($userId);

            if (!$success) {
                $this->transactionManager->rollback();
                return self::respondWithError(40701);
            }

            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => 10701,
            ];
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Error verifying account.', ['exception' => $e]);
            return self::respondWithError(40701);
        }
    }

    public function deleteUnverifiedUsers(): bool|array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        try {
            $this->transactionManager->beginTransaction();

            $this->userMapper->deleteUnverifiedUsers();

            $this->transactionManager->commit();

            $this->logger->info('Unverified users deleted.');
            return true;
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Error deleting unverified users.', ['exception' => $e]);
            return false;
        }
    }


    public function updateUserPreferences(?array $args = []): array {

        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        if (empty($args)) {
            return self::respondWithError(30101);
        }
        $contentFilterService = new ContentFilterServiceImpl();

        $this->logger->info('UserService.updateUserPreferences started');

        $newUserPreferences = $args['userPreferences'];
        $contentFiltering = $newUserPreferences['contentFilteringSeverityLevel'] ?? null;
        $shownOnboardingsIn = $newUserPreferences['shownOnboardings'] ?? null;
        
        try {
            $this->transactionManager->beginTransaction();

            $userPreferences = $this->userPreferencesMapper->loadPreferencesById($this->currentUserId);
            if (!$userPreferences) {
                $this->logger->error('UserService.updateUserPreferences: failed to load user preferences for updating');
                return $this->respondWithError(40301); // 402xx
            }

            if ($contentFiltering && !empty($contentFiltering)) {
                $contentFilteringSeverityLevel = $contentFilterService->getContentFilteringSeverityLevel($contentFiltering);

                $userPreferences->setContentFilteringSeverityLevel($contentFilteringSeverityLevel);
                $userPreferences->setUpdatedAt();
            }

            if (is_array($shownOnboardingsIn) && !empty($shownOnboardingsIn)) {
                $this->updateOnboardings($userPreferences, $shownOnboardingsIn);
            }


            $resultPreferences = ($this->userPreferencesMapper->update($userPreferences))->getArrayCopy();

            $contentFilteringSeverityLevelString = $contentFilterService->getContentFilteringStringFromSeverityLevel($resultPreferences['contentFilteringSeverityLevel']);
            
            $resultPreferences['contentFilteringSeverityLevel'] = $contentFilteringSeverityLevelString;

            $this->logger->info('User preferences updated successfully', ['userId' => $this->currentUserId]);
            
            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => 11014,  // 102xx
                'affectedRows' => $resultPreferences,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to update user preferences', ['exception' => $e]);
            $this->transactionManager->rollback();
            return self::respondWithError(41016); // 402xx
        }
    }

    private function updateOnboardings($userPreferences, array $shownOnboardingsIn): void
    {
        $available = ConstantsConfig::onboarding()['AVAILABLE_ONBOARDINGS'] ?? [];
        if (empty($available)) {
            $this->logger->error('updateUserPreferences: AVAILABLE_ONBOARDINGS list is empty');
            throw new \RuntimeException('No available onboardings configured', 40301);// List is empty, response code = 4XXXX
        }

        foreach ($shownOnboardingsIn as $onboarding) {
            $val = (string) $onboarding;
            if (!in_array($val, $available, true)) {
                $this->logger->warning('updateUserPreferences: invalid onboarding value', [
                    'value'     => $val,
                    'available' => $available,
                ]);
                throw new \InvalidArgumentException('INVALID_ONBOARDING_VALUE', 31011);
            }
        }

        $currentShown = $userPreferences->getOnboardingsWereShown();
        if (!is_array($currentShown)) {
            $currentShown = empty($currentShown) ? [] : (array) $currentShown;
        }

        $currentShown = array_values(array_filter(array_map('strval', $currentShown)));
        $incoming     = array_values(array_filter(array_map('strval', $shownOnboardingsIn)));

        $set = array_fill_keys($currentShown, true);
        foreach ($incoming as $onboarding) {
            $set[$onboarding] = true;
        }
        $merged = array_keys($set);

        if ($merged !== $currentShown) {
            $userPreferences->setOnboardingsWereShown($merged);
            $userPreferences->setUpdatedAt();
        }
    }

    public function setPassword(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        if (empty($args)) {
            return self::respondWithError(30101);
        }

        $this->logger->info('UserService.setPassword started');

        $newPassword = $args['password'] ?? null;
        $currentPassword = $args['expassword'] ?? null;

        $passwordValidation = $this->validatePassword($newPassword);
        if ($passwordValidation['status'] === 'error') {
            return $passwordValidation;
        }

        if ($newPassword === $currentPassword) {
            return self::respondWithError(31004);
        }

        $user = $this->userMapper->loadById($this->currentUserId);

        if (!$user) {
            $this->logger->warning('User not found', ['userId' => $this->currentUserId]);
            return self::createSuccessResponse(21001);
        }

        if (!$this->validatePasswordMatch($currentPassword, $user->getPassword())) {
            return self::respondWithError(31001);
        }

        try {
            $this->transactionManager->beginTransaction();
            $user->validatePass($args);
            $this->userMapper->updatePass($user);

            $this->logger->info('User password updated successfully', ['userId' => $this->currentUserId]);
            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => 11005,
            ];
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
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
            return self::respondWithError(30101);
        }

        $this->logger->info('UserService.setEmail started');

        $email = $args['email'] ?? null;
        $exPassword = $args['password'] ?? null;    
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Invalid email format', ['email' => $email]);
            return self::respondWithError(30224);
        }
        
        $user = $this->userMapper->loadById($this->currentUserId);
        if ($email === $user->getMail()) {
            return self::respondWithError(31005);
        }

        if ($this->userMapper->isEmailTaken($email)) {
            $this->logger->warning('Email already in use', ['email' => $email]);
            return self::respondWithError(31003);
        }

        $user = $this->userMapper->loadById($this->currentUserId);
        if (!$user) {
            $this->logger->warning('User not found', ['userId' => $this->currentUserId]);
            return self::createSuccessResponse(21001);
        }

        if ($email === $user->getMail()) {
            return self::createSuccessResponse(21004);
        }

        if (!$this->validatePasswordMatch($exPassword, $user->getPassword())) {
            return self::respondWithError(31001);
        }

        try {
            $this->transactionManager->beginTransaction();
            $user->setMail($email);
            $data = $this->userMapper->updateProfil($user);
            $affectedRows = $data->getArrayCopy();

            $this->logger->info('User email updated successfully', ['userId' => $this->currentUserId, 'email' => $email]);
            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => 11006,
                'affectedRows' => $affectedRows,
            ];
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
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
            return self::respondWithError(30101);
        }

        $this->logger->info('UserService.setUsername started');

        $username = trim($args['username']);
        $password = $args['password'] ?? null;

        try {
            $this->transactionManager->beginTransaction();

            $validationResult = new User(['username' => $username], ['username']);

            $user = $this->userMapper->loadById($this->currentUserId);
            if (!$user) {
                return self::createSuccessResponse(21001);
            }

            if ($username === $user->getName()) {
                return self::respondWithError(31006);
            }

            if (!$this->validatePasswordMatch($password, $user->getPassword())) {
                return self::respondWithError(31001);
            }

            $slug = $this->generateUniqueSlug($username);
            if (!$slug) {
                return self::respondWithError(41010);
            }

            $user->setName($username);
            $user->setSlug($slug);

            $data = $this->userMapper->updateProfil($user);
            $affectedRows = $data->getArrayCopy();

            $this->logger->info('Username updated successfully', ['id' => $this->currentUserId, 'username' => $username, 'slug' => $slug]);

            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => 11007,
                'affectedRows' => $affectedRows,
            ];
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Failed to update username', ['exception' => $e]);
            return self::respondWithError(30202);
        }
    }

    public function deleteAccount(string $expassword): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        if (empty($expassword)) {
            return self::respondWithError(30101);
        }

        $this->logger->info('UserService.deleteAccount started');

        $userId = $this->currentUserId;

        $user = $this->userMapper->loadById($userId);
        if (!$user) {
            return self::createSuccessResponse(21001);
        }

        if (!$this->validatePasswordMatch($expassword, $user->getPassword())) {
            return self::respondWithError(31001);
        }

        try {
            $this->transactionManager->beginTransaction();
            $this->userMapper->delete($userId);
            $this->logger->info('User deleted successfully', ['userId' => $userId]);

            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => 11012,
            ];
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Failed to delete user', ['exception' => $e]);
            return self::respondWithError(41011);
        }
    }

    public function Profile(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        $userId = $args['userid'] ?? $this->currentUserId;
        $postLimit = min(max((int)($args['postLimit'] ?? 4), 1), 10);
        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $this->logger->info('UserService.Profile started');

        if (!self::isValidUUID($userId)) {
            $this->logger->warning('Invalid UUID for profile', ['userId' => $userId]);
            return self::respondWithError(30102);
        }
        if (!$this->userMapper->isUserExistById($userId)) {
            $this->logger->warning('User not found for Follows', ['userId' => $userId]);
        return self::respondWithError(31007);
        }

        try {
            $profileData = $this->userMapper->fetchProfileData($userId, $this->currentUserId,$contentFilterBy)->getArrayCopy();
            $this->logger->info("Fetched profile data", ['profileData' => $profileData]);

            $posts = $this->postMapper->fetchPostsByType($this->currentUserId,$userId, $postLimit,$contentFilterBy);

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
            return $this->createSuccessResponse(21001, []);
        }
    }

    public function Follows(?array $args = []): array
    {
        $this->logger->info('UserService.Follows started');

        $userId = $args['userid'] ?? $this->currentUserId;
        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
        $contentFilterBy = $args['contentFilterBy'] ?? null;
        $contentFilterService = new ContentFilterServiceImpl(new ListPostsContentFilteringStrategy());
        if($contentFilterService->validateContentFilter($contentFilterBy) == false){
            return $this->respondWithError(30103);
        }

        if (!self::isValidUUID($userId)) {
            $this->logger->warning('Invalid UUID provided for Follows', ['userId' => $userId]);
            return self::respondWithError(30201);
        }
        if (!$this->userMapper->isUserExistById($userId)) {
            $this->logger->warning('User not found for Follows', ['userId' => $userId]);
            return self::respondWithError(31007);
        }
        try {
            $followers = $this->userMapper->fetchFollowers($userId, $this->currentUserId, $offset, $limit,$contentFilterBy);
            $following = $this->userMapper->fetchFollowing($userId, $this->currentUserId, $offset, $limit,$contentFilterBy);
            
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
        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $this->logger->info('Fetching friends list', ['currentUserId' => $this->currentUserId, 'offset' => $offset, 'limit' => $limit]);

        try {
            $users = $this->userMapper->fetchFriends($this->currentUserId, $offset, $limit,$contentFilterBy);

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
            return self::createSuccessResponse(21101);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch friends', ['exception' => $e->getMessage()]);
            return self::respondWithError(41107);
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
                    'ResponseCode' => 11102,
                    'affectedRows' => $users,
                ];
            }

            $this->logger->info('No friends found @ all');
            return self::createSuccessResponse(21101);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch friends', ['exception' => $e->getMessage()]);
            return self::respondWithError(41107);
        }
    }

    public function fetchAllAdvance(?array $args = []): array
    {

        $this->logger->info('UserService.fetchAllAdvance started');

        $contentFilterBy = $args['contentFilterBy'] ?? null;
        $contentFilterService = new ContentFilterServiceImpl(new ListPostsContentFilteringStrategy());
        if($contentFilterService->validateContentFilter($contentFilterBy) == false){
            return $this->respondWithError(30103);
        }

        try {
            $users = $this->userMapper->fetchAllAdvance($args, $this->currentUserId,$contentFilterBy);
            $fetchAll = array_map(fn(UserAdvanced $user) => $user->getArrayCopy(), $users);

            if ($fetchAll) {
                return [
                    'status' => 'success',
                    'counter' => count($fetchAll),
                    'ResponseCode' => 11009,
                    'affectedRows' => $fetchAll,
                ];
            }

            return $this->respondWithError(31007);
        } catch (\Throwable $e) {
            return self::respondWithError(41207);
        }
    }

    public function fetchAll(?array $args = []): array
    {

        $this->logger->info('UserService.fetchAll started');

        try {
            $users = $this->userMapper->fetchAll($this->currentUserId, $args);
            $fetchAll = array_map(fn(User $user) => $user->getArrayCopy(), $users);

            if ($fetchAll) {
                return [
                    'status' => 'success',
                    'counter' => count($fetchAll),
                    'ResponseCode' => 11009,
                    'affectedRows' => $fetchAll,
                ];
            }

            return self::createSuccessResponse(21001);
        } catch (\Throwable $e) {
            return self::respondWithError(41207);
        }
    }

    /**
     * Reset password token request for NON logged in user
     * 
     * Generate Token for reset password and store on 
     * 
     * @param string $email
     * 
     * @return array
     */
    public function requestPasswordReset(string $email): array
    {
        $this->logger->info('UserService.requestPasswordReset started');

        $updatedAt = $this->getCurrentTimestamp();
        $expiresAt = $this->getFutureTimestamp('+1 hour');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Invalid email format', ['email' => $email]);
            return self::respondWithError(30104);
        }

        try {
            $this->transactionManager->beginTransaction();

            $user = $this->userMapper->loadByEmail($email);
            
            if (!$user) {
                $this->logger->warning('Invalid user', ['email' => $email]);
                return $this->genericPasswordResetSuccessResponse();
            }

            $userId = $user->getUserId();

            $passwordAttempt = $this->userMapper->checkForPasswordResetExpiry($userId);
            $token = bin2hex(random_bytes(32));

            if (!$passwordAttempt) {
                $this->userMapper->createResetRequest($userId, $token, $updatedAt, $expiresAt);

                $data = [
                    'code' => $token,
                ];
                $this->userMapper->sendPasswordResetEmail($email, $data);
                
                $this->transactionManager->commit();
                return $this->genericPasswordResetSuccessResponse();
            }

            // Check for rate limiting: 1st attempt 
            if ($this->userMapper->isFirstAttemptTooSoon($passwordAttempt)) {
                $this->transactionManager->rollback();
                return $this->userMapper->rateLimitResponse(1);
            }

            // 2nd attempt 
            if ($this->userMapper->isSecondAttemptTooSoon($passwordAttempt)) {
                $this->transactionManager->rollback();
                return $this->userMapper->rateLimitResponse(10, $passwordAttempt['last_attempt']);
            }

            // Too many attempts made without using the token
            if ($passwordAttempt['attempt_count'] >= 3 && !$passwordAttempt['collected']) {
                $this->transactionManager->rollback();
                return $this->userMapper->tooManyAttemptsResponse();
            }

            $this->userMapper->updateAttempt($passwordAttempt);

            if(isset($passwordAttempt['token'])){
                $token = $passwordAttempt['token'];
                $data = [
                    'code' => $token,
                ];

                $this->userMapper->sendPasswordResetEmail($email, $data);
            }
            $this->transactionManager->commit();
            return $this->genericPasswordResetSuccessResponse();

        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Unexpected error during password reset request', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'updatedat' => $updatedAt,
                'expires_at' => $expiresAt,
            ]);
        }
        return $this->genericPasswordResetSuccessResponse();

    }
    
    /**
     * Standard success response (avoids revealing account existence).
     */
    public function genericPasswordResetSuccessResponse(): array
    {
        return [
            'status' => 'success',
            'ResponseCode' => 11901
        ];
    }


    /**
     * Returns the current timestamp in microsecond precision.
     */
    private function getCurrentTimestamp(): string
    {
        return date("Y-m-d H:i:s.u");
    }

    /**
     * Returns a timestamp relative to now (e.g., +1 hour).
     */
    private function getFutureTimestamp(string $modifier): string
    {
        return date("Y-m-d H:i:s.u", strtotime($modifier));
    }


    /**
     * Update password for NON logged in user.
     *
     * Expects an array with:
     * - 'password': new password string
     * - 'token': password reset token sent to user's email
     *
     * @param array{
     *     password?: string,
     *     token?: string
     * }|null $args  The input data including reset token and new password
     *
     * @return array{
     *     status: string,
     *     ResponseCode: int
     * }
     *
     * Response Codes:
     * - 11005: Password updated successfully.
     * - 21001: No user found for token.
     * - 31904: Invalid or expired reset token.
     * - 41004: Unexpected error during password reset.
     */
    public function resetPassword(?array $args): array
    {
        $this->logger->info('UserService.resetPassword started');

        $newPassword = $args['password'] ?? null;

        $passwordValidation = $this->validatePassword($newPassword);

        if ($passwordValidation['status'] === 'error') {
            return $passwordValidation;
        }

        try {
            $this->transactionManager->beginTransaction();

            $newUser = new User();
            $newUser->setPassword($newPassword);
            $newUser->validatePass($args);

            $request = $this->userMapper->getPasswordResetRequest($args['token']);

            if (!$request) {
                $this->userMapper->deletePasswordResetToken($args['token']);
                $this->transactionManager->rollback();
                return [
                    'status' => 'error',
                    'ResponseCode' => 31904
                ];
            }
            $user = $this->userMapper->loadById($request['user_id']);

            if (!$user) {
                $this->logger->warning('User not found', ['userId' => $request['user_id']]);
                $this->transactionManager->commit();
                return self::createSuccessResponse(21001);
            }

            $user->validatePass($args);
            $this->userMapper->updatePass($user);

            $this->userMapper->deletePasswordResetToken($args['token']);

            $this->logger->info('User password updated successfully', ['userId' => $this->currentUserId]);
            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => 11005,
            ];
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Failed to update user password', ['exception' => $e]);
            return self::respondWithError(41004);
        }
    }

}
