<?php

namespace Fawaz\App;

use Fawaz\App\Interfaces\ProfileService;
use Fawaz\App\Profile;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\UserMapper;
use Fawaz\GraphQL\Context;
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
use Fawaz\App\UserService;

final class ProfileServiceImpl implements ProfileService
{
    use ResponseHelper;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected ProfileRepository $profileRepository,
        protected UserService $userService,
        protected UserMapper $userMapper,
    ) {
    }        

    public function profile(array $args, Context $ctx): Profile | ErrorResponse
    {
        $this->logger->info('ProfileService.Profile started');

        $userId = $args['userid'] ?? $ctx->currentUserId;
        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $contentFilterCase = $userId === $ctx->currentUserId ? ContentFilteringCases::myprofile : ContentFilteringCases::searchById;

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
            $ctx->currentUserId,
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
                $ctx->currentUserId,
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

    public function listUsers(array $args, Context $ctx): array
    {
        $username = $args['username'] ?? null;
        $contentFilterBy = $args['contentFilterBy'] ?? null;
        $userId = $args['userid'] ?? null;
        $status = $args['status'] ?? null;
        $verified = $args['verified'] ?? null;
        $args['limit'] = min(max((int)($args['limit'] ?? 10), 1), 20);

        if (!empty($username) && !empty($userId)) {
            return $this::respondWithError(31012);
        }

        $this->logger->debug('ProfileService.listUsers started');

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
            $ctx->currentUserId,
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
            $users = $this->userMapper->fetchAll($ctx->currentUserId, $args, $specs);
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

    public function listUsersAdmin(array $args, Context $ctx): array
    {
        $username = $args['username'] ?? null;
        $userId = $args['userid'] ?? null;

        if (!empty($username) && !empty($userId)) {
            return $this::respondWithError(31012);
        }

        if (!empty($userId)) {
            $args['uid'] = $userId;
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

    public function searchUser(array $args, Context $ctx): array
    {
        if (empty($args['username']) && empty($args['userid']) && empty($args['email']) && !isset($args['status']) && !isset($args['verified']) && !isset($args['ip'])) {
            return $this::respondWithError(30102);
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

        if (!empty($ip) && !filter_var($ip, FILTER_VALIDATE_IP)) {
            return $this::respondWithError(30257);//"The IP '$ip' is not a valid IP address."
        }

        $args['limit'] = min(max((int)($args['limit'] ?? 10), 1), 20);

        $this->logger->debug('Query.resolveSearchUser started');

        if ($ctx->roles === 16) {
            $args['includeDeleted'] = true;
        }

        $contentFilterCase = ContentFilteringCases::searchByMeta;
        if (!empty($userId)) {
            $contentFilterCase = ContentFilteringCases::searchById;
            if ($userId == $ctx->currentUserId) {

                $contentFilterCase = ContentFilteringCases::myprofile;
            }
            $args['uid'] = $userId;
        }

        $contentFilterBy = $args['contentFilterBy'] ?? null;

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
            $ctx->currentUserId
        );
        $illegalContentFilterSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            ContentType::user
        );
        $specs = [
            $illegalContentFilterSpec,
            $systemUserSpec,
            $deletedUserSpec,
            $hiddenContentFilterSpec,
        ];


        try {
            $users = $this->userMapper->fetchAll($ctx->currentUserId, $args, $specs);
            $usersArray = [];
            foreach ($users as $profile) {
                if ($profile instanceof ProfileReplaceable) {
                    ContentReplacer::placeholderProfile($profile, $specs);
                }
                $usersArray[] = $profile->getArrayCopy();
            }

            if (!empty($usersArray)) {
                $this->logger->info('Query.resolveSearchUser successful', ['userCount' => count($usersArray)]);
                return [
                    'status' => 'success',
                    'counter' => count($usersArray),
                    'ResponseCode' => "11009",
                    'affectedRows' => $usersArray,
                ];
            } else {
                if ($userId) {
                    return self::respondWithError(31007);
                }
                return self::createSuccessResponse(21001);
            }
        } catch (\Throwable $e) {
            return self::respondWithError(41207);
        }
    }
}