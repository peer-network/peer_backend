<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Resolvers;

use Fawaz\App\Interfaces\ProfileService;
use Fawaz\App\UserInfoService;
use Fawaz\App\UserService;
use Fawaz\Database\UserMapper;
use Fawaz\GraphQL\Context;
use Fawaz\GraphQL\ResolverProvider;
use Fawaz\GraphQL\Support\ResolverHelpers;
use Fawaz\Utils\ErrorResponse;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;

/**
 * Query resolvers for User domain: listUsersV2, getProfile, getUserInfo.
 */
class UserQueryResolver implements ResolverProvider
{
    use ResolverHelpers;
    use ResponseHelper;
    public function __construct(
        private readonly PeerLoggerInterface $logger,
        private readonly ProfileService $profileService,
        private readonly UserMapper $userMapper,
        private readonly UserInfoService $userInfoService,
        private readonly UserService $userService,
    ) {
    }

    /** @return array<string, array<string, callable>> */
    public function getResolvers(): array
    {
        return [
            'Query' => [
                'listUsersV2' => $this->withAuth(
                    null,
                    fn (mixed $root, array $args, Context $ctx) => $this->profileService->listUsers($args)
                ),
                'getUserInfo' => $this->withAuth(
                    null,
                    fn (mixed $root, array $args, Context $ctx) => $this->resolveUserInfo()
                ),
                'getReferralInfo' => $this->withAuth(
                    null, 
                    fn (mixed $root, array $args, Context $ctx) => $this->resolveReferralInfo($args, $ctx)
                ),
                
                'listFollowRelations' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (mixed $root, array $args) => $this->resolveFollows($args)
                    )
                ),
                'listBlockedUsers' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (mixed $root, array $args, Context $ctx) => $this->resolveBlocklist($args)
                    )
                ),
                'getProfile' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (mixed $root, array $validated, Context $ctx) => $this->resolveProfile($validated)
                    )
                ),
            ],
        ];
    }

    private function resolveUserInfo(): ?array
    {
        $this->logger->debug('UserQueryResolver.resolveUserInfo started');
        $results = $this->userInfoService->loadInfoById();

        if (isset($results['status']) && $results['status'] === 'success') {
            return $results;
        }
        if (isset($results['status']) && $results['status'] === 'error') {
            return $results;
        }
        // Fallback consistent shape
        return [
            'status' => 'error',
            'ResponseCode' => 40301,
        ];
    }

    private function resolveProfile(array $args): array
    {
        $this->logger->debug('UserQueryResolver.resolveProfile started');

        $result = $this->profileService->profile($args);
        if ($result instanceof ErrorResponse) {
            return $result->response;
        }

        return [
            'status' => 'success',
            'ResponseCode' => 11008,
            'affectedRows' => $result->getArrayCopy(),
        ];
    }

    protected function resolveReferralInfo(array $args, Context $ctx): ?array
    {

        $this->logger->debug('Query.resolveReferralInfo started');

        try {
            $userId = $ctx->userId;
            $this->logger->info('Current userId in resolveReferralInfo', [
                'userId' => $userId,
            ]);


            $info = $this->userMapper->getReferralInfoByUserId($userId);
            if (empty($info)) {
                return $this::createSuccessResponse(21002);
            }

            $response = [
                'referralUuid' => $info['referral_uuid'] ?? '',
                'referralLink' => $info['referral_link'] ?? '',
                'status' => 'success',
                'ResponseCode' => "11011"
            ];

            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('Query.resolveReferralInfo exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this::respondWithError(41013);
        }
    }

    protected function resolveFollows(array $args): ?array
    {

        $this->logger->debug('Query.resolveFollows started');

        $results = $this->userService->Follows($args);


        if ($results instanceof ErrorResponse) {
            return $results->response;
        }

        $this->logger->info('Query.resolveProfile successful');
        return $results;
    }
    protected function resolveBlocklist(array $args): ?array {
        $this->logger->debug('Query.resolveBlocklist started');

        $response = $this->userInfoService->loadBlocklist($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (empty($response['counter'])) {
            return $this::createSuccessResponse(11107, [], false);
        }

        if (is_array($response) || !empty($response)) {
            return $response;
        }

        $this->logger->error('Query.resolveBlocklist No data found');
        return $this::respondWithError(41105);
    }
}
