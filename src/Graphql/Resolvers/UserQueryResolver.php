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
use function PHPUnit\Framework\returnArgument;

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
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) => $this->profileService->listUsers($validated,$ctx)
                    )
                ),
                'getUserInfo' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) => $this->resolveUserInfo($ctx)
                    )
                ),
                'getReferralInfo' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (mixed $root, array $args, Context $ctx) => $this->resolveReferralInfo($args, $ctx)
                    )
                ),
                'listFollowRelations' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (mixed $root, array $args, Context $ctx) => $this->resolveFollows($args, $ctx)
                    )
                ),
                'listBlockedUsers' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) => $this->resolveBlocklist($validated, $ctx)
                    )
                ),
                'getProfile' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) => $this->resolveProfile($validated,$ctx)
                    )
                ),
                'searchUser' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) => $this->profileService->searchUser($validated,$ctx)
                    )
                ),
                'searchUserAdmin' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) => $this->profileService->searchUser($validated, $ctx)
                    )
                ),
                'listUsersAdminV2' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) =>  $this->profileService->listUsersAdmin($validated,$ctx)
                    )
                ),
                'listUsers' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) => $this->profileService->listUsers($validated,$ctx)
                    )
                ),
                'listFriends' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) => $this->resolveFriends($validated, $ctx)
                    )
                ),
                'allfriends' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) => $this->resolveAllFriends($validated, $ctx)
                    )
                ),
                'referralList' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) => $this->profileService->userReferralList($validated,$ctx)
                    )
                ),
            ],
        ];
    }

    private function resolveUserInfo(Context $ctx): array
    {
        $this->logger->debugWithUser('UserQueryResolver.resolveUserInfo started', $ctx->currentUserId);
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

    private function resolveProfile(array $args, Context $ctx): array
    {
        $this->logger->debugWithUser('UserQueryResolver.resolveProfile started',$ctx->currentUserId);

        $result = $this->profileService->profile($args,$ctx);

        if ($result instanceof ErrorResponse) {
            return $result->response;
        }

        return $this->createSuccessResponse(
            11008,
            $result->getArrayCopy()
        );
    }

    protected function resolveReferralInfo(array $args, Context $ctx): ?array
    {

        $this->logger->debugWithUser('Query.resolveReferralInfo started', $ctx->currentUserId);

        try {
            $userId = $ctx->currentUserId;
            $this->logger->info('Current userId in resolveReferralInfo', [
                'userId' => $userId,
            ]);


            $info = $this->userMapper->getReferralInfoByUserId($ctx->currentUserId);
            if (empty($info)) {
            return $this->createSuccessResponse(21002);
            }

            return $this->createSuccessResponse(11011, [
                'referralUuid' => $info['referral_uuid'] ?? '',
                'referralLink' => $info['referral_link'] ?? '',
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Query.resolveReferralInfo exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return $this->respondWithError(41013);
        }
    }

    protected function resolveFollows(array $args, Context $ctx): ?array
    {
        $this->logger->debugWithUser('Query.resolveFollows started', $ctx->currentUserId);

        $results = $this->userService->Follows($args);

        if ($results instanceof ErrorResponse) {
            return $results->response;
        }

        $this->logger->info('Query.resolveProfile successful');
        return $results;
    }
    protected function resolveBlocklist(array $args, Context $ctx): ?array {
        $this->logger->debugWithUser('Query.resolveBlocklist started', $ctx->currentUserId);

        $response = $this->userInfoService->loadBlocklist($args);
        if (is_array($response)) {
            return $response;
        }

        $this->logger->errorWithUser('Query.resolveBlocklist invalid response', $ctx->currentUserId);
        return $this->respondWithError(41105);
    }

    protected function resolveFriends(array $args, Context $ctx): ?array
    {
        $this->logger->debugWithUser('Query.resolveFriends started', $ctx->currentUserId);

        $results = $this->userService->getFriends($args);
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveFriends successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $results;
        }

        $this->logger->warning('Query.resolveFriends Users not found');
        return $this->createSuccessResponse(21101);
    }

    protected function resolveAllFriends(array $args, Context $ctx): ?array
    {
        $this->logger->debugWithUser('Query.resolveAllFriends started', $ctx->currentUserId);

        $results = $this->userService->getAllFriends($args);
        if (isset($results['status']) && $results['status'] === 'success') {
            $this->logger->info('Query.resolveAllFriends successful');

            return $results;
        }

        if (isset($results['status']) && $results['status'] === 'error') {
            return $results;
        }

        $this->logger->warning('Query.resolveAllFriends No listFriends found');
        return $this->createSuccessResponse(21101);
    }
}
