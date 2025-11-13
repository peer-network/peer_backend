<?php

namespace Fawaz\App;

use Fawaz\App\Interfaces\ProfileService;
use Fawaz\App\Profile;
use Fawaz\Database\UserMapper;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent\HiddenContentFilterSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent\NormalVisibilityStatusSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\IllegalContent\IllegalContentFilterSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\SystemUserSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\DeletedUserSpec;
use Fawaz\App\ValidationException;
use Fawaz\Services\ContentFiltering\Replacers\ContentReplacer;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Database\Interfaces\ProfileRepository;
use Fawaz\Utils\ErrorResponse;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\App\UserService;

final class ProfileServiceImpl implements ProfileService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected ProfileRepository $profileRepository,
        protected UserService $userService,
        protected UserMapper $userMapper,
    ) {}

    public function setCurrentUserId(string $userId): void {
        $this->currentUserId = $userId;
    }

    public function profile(array $args): Profile | ErrorResponse {
        $this->logger->info('ProfileService.Profile started');
        
        $userId = $args['userid'] ?? $this->currentUserId;
        $contentFilterBy = $args['contentFilterBy'] ?? null;
        
        $contentFilterCase = $userId === $this->currentUserId ? ContentFilteringCases::myprofile : ContentFilteringCases::searchById;
        
        $deletedUserSpec = new DeletedUserSpec(
            $contentFilterCase,
            ContentType::user
        );
        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::user
        );

        $hiddenContentFilterSpec = new HiddenContentFilterSpec(
            $contentFilterCase,
            $contentFilterBy,
            ContentType::user,
            $this->currentUserId,
        );
        
        $illegalContentSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            ContentType::user
        );
        $normalVisibilityStatusSpec = new NormalVisibilityStatusSpec($contentFilterBy);

        $specs = [
            $illegalContentSpec,
            $systemUserSpec,
            $deletedUserSpec,
            $hiddenContentFilterSpec,
            $normalVisibilityStatusSpec
        ];

        try {
            $profileData = $this->profileRepository->fetchProfileData(
                $userId,
                $this->currentUserId,
                $specs
            );

            if (!$profileData) {
                $this->logger->warning('Query.resolveProfile User not found');
                return self::respondWithErrorObject(31007);
            }
            /** @var Profile $profileData */
            // Hint analyzers: keep concrete type after by-ref mutation
            ContentReplacer::placeholderProfile($profileData, $specs);

            $this->logger->debug("Fetched profile data", ['userid' => $profileData->getUserId()]);

            return $profileData;

        } catch (ValidationException $e) {
            $this->logger->error('Validation error: Failed to fetch profile data', [
                'userid' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return $this::respondWithErrorObject(31007);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch profile data', [
                'userid' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return $this::respondWithErrorObject(41007);
        }
    }

    private function validateOffsetAndLimit(array $args = []): ?array
    {
        $offset = isset($args['offset']) ? (int)$args['offset'] : null;
        $limit = isset($args['limit']) ? (int)$args['limit'] : null;

        $paging = ConstantsConfig::paging();
        $minOffset = $paging['OFFSET']['MIN'];
        $maxOffset = $paging['OFFSET']['MAX'];
        $minLimit = $paging['LIMIT']['MIN'];
        $maxLimit = $paging['LIMIT']['MAX'];

        if ($offset !== null) {
            if ($offset < $minOffset || $offset > $maxOffset) {
                return $this::respondWithError(30203);
            }
        }

        if ($limit !== null) {
            if ($limit < $minLimit || $limit > $maxLimit) {
                return $this::respondWithError(30204);
            }
        }

        return null;
    }

    public function listUsers(array $args): array
    {
        if ($this->currentUserId === null) {
            return $this::respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $username = isset($args['username']) ? trim($args['username']) : null;
        $usernameConfig = ConstantsConfig::user()['USERNAME'];
        $userId = $args['userid'] ?? null;
        $email = $args['email'] ?? null;
        $status = $args['status'] ?? null;
        $verified = $args['verified'] ?? null;
        $ip = $args['ip'] ?? null;

        if (!empty($username) && !empty($userId)) {
            return $this::respondWithError(31012);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            return $this::respondWithError(30201);
        }

        if ($username !== null && (strlen($username) < $usernameConfig['MIN_LENGTH'] || strlen($username) > $usernameConfig['MAX_LENGTH'])) {
            return $this::respondWithError(30202);
        }

        if ($username !== null && !preg_match('/' . $usernameConfig['PATTERN'] . '/u', $username)) {
            return $this::respondWithError(30202);
        }


        if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this::respondWithError(30257);
        }

        $args['limit'] = min(max((int)($args['limit'] ?? 10), 1), 20);

        $this->logger->debug('ProfileService.listUsers started');

        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $contentFilterCase = ContentFilteringCases::searchByMeta;
        if (!empty($userId)) {
            $contentFilterCase = ContentFilteringCases::searchById;
            $args['uid'] = $userId;
        }        

        $deletedUserSpec = new DeletedUserSpec(
            $contentFilterCase,
            ContentType::user
        );
        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::user
        );
        $hiddenContentFilterSpec = new HiddenContentFilterSpec(
            $contentFilterCase,
            $contentFilterBy,
            ContentType::user,
            $this->currentUserId,
        );
        $illegalContentSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            ContentType::user
        );
        $normalVisibilityStatusSpec = new NormalVisibilityStatusSpec($contentFilterBy);
        
        $specs = [
            $illegalContentSpec,
            $systemUserSpec,
            $deletedUserSpec,
            $hiddenContentFilterSpec,
            $normalVisibilityStatusSpec
        ];


        try {
            $users = $this->userMapper->fetchAll($this->currentUserId, $args, $specs);
            $usersArray = [];
            foreach ($users as $profile) {
                if ($profile instanceof ProfileReplaceable) {
                    ContentReplacer::placeholderProfile($profile, $specs);
                }
                $usersArray[] = $profile->getArrayCopy();
            }

            if ($usersArray) {
                $this->logger->info('ProfileService.listUsers successful', ['userCount' => count($usersArray)]);

                return [
                    'status' => 'success',
                    'counter' => count($usersArray),
                    'ResponseCode' => "11009",
                    'affectedRows' => $usersArray,
                ];
            }

            return self::createSuccessResponse(21001);
        } catch (\Throwable $e) {
            return self::respondWithError(41207);
        }
    }

    public function listUsersAdmin(array $args): array
    {
        if ($this->currentUserId === null) {
            return $this::respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $username = isset($args['username']) ? trim($args['username']) : null;
        $usernameConfig = ConstantsConfig::user()['USERNAME'];
        $userId = $args['userid'] ?? null;
        $ip = $args['ip'] ?? null;

        if (!empty($username) && !empty($userId)) {
            return $this::respondWithError(31012);
        }

        if ($userId !== null && !self::isValidUUID($userId)) {
            return $this::respondWithError(30201);
        }

        if ($username !== null && (strlen($username) < $usernameConfig['MIN_LENGTH'] || strlen($username) > $usernameConfig['MAX_LENGTH'])) {
            return $this::respondWithError(30202);
        }

        if ($username !== null && !preg_match('/' . $usernameConfig['PATTERN'] . '/u', $username)) {
            return $this::respondWithError(30202);
        }

        if (!empty($userId)) {
            $args['uid'] = $userId;
        }

        if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this::respondWithError(30257);
        }

        $args['limit'] = min(max((int)($args['limit'] ?? 10), 1), 20);
        $args['includeDeleted'] = true;

        $this->logger->debug('ProfileService.listUsersAdmin started');

        $specs = [];

        $data = $this->userService->fetchAllAdvance($args);

        if (!empty($data)) {
            $this->logger->info('ProfileService.listUsersAdmin.fetchAll successful', ['userCount' => count($data)]);
            foreach ($data as $i => $item) {
                if ($item instanceof ProfileReplaceable) {
                    ContentReplacer::placeholderProfile($item, $specs);
                }
            }
            return $data;
        }

        return $this::createSuccessResponse(21001);
    }
}
