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

class UserQueryResolver implements ResolverProvider
{
    use ResolverHelpers;
    use ResponseHelper;
    public function __construct(
        private readonly PeerLoggerInterface $logger,
        private readonly ProfileService $profileService,
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

        if ($results instanceof ErrorResponse) {
            return $results->response;
        }
        
        return $this->createSuccessResponse(
            11002,
            $results
        );
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

    protected function resolveReferralInfo(array $args, Context $ctx): array
    {
        $this->logger->debugWithUser('UserQueryResolver.resolveReferralInfo started', $ctx->currentUserId);

        $result = $this->profileService->referralInfo($args,$ctx);

        if ($result instanceof ErrorResponse) {
            return $result->response;
        }
        
        if (empty($result)) {
            return $this->createSuccessResponse(21002);
        }

        return $this->createSuccessResponse(11011, [
            'referralUuid' => $result['referral_uuid'],
            'referralLink' => $result['referral_link']
        ]);
    }

    protected function resolveFollows(array $args, Context $ctx): array
    {
        $this->logger->debugWithUser('UserQueryResolver.resolveFollows started', $ctx->currentUserId);

        $result = $this->userService->Follows($args);

        if ($result instanceof ErrorResponse) {
            return $result->response;
        }

        $result['counter'] = count($result['followers']) + count($result['following']);

        return self::createSuccessResponse(
            11101,
            $result,
            true,
            'counter'
        );
    }
    protected function resolveBlocklist(array $args, Context $ctx): array
    {
        $this->logger->debugWithUser('UserQueryResolver.resolveBlocklist started', $ctx->currentUserId);

        $result = $this->userInfoService->loadBlocklist($args);

        if ($result instanceof ErrorResponse) {
            return $result->response;
        }

        $result['counter'] = count($result['blockedBy']) + count($result['iBlocked']);

        $this->logger->info('loadBlocklist list retrieved successfully', ['userCount' => count($result)]);
        
        return self::createSuccessResponse(
            11107,
            $result,
            true,
            'counter'
        );
    }

    protected function resolveFriends(array $args, Context $ctx): ?array
    {
        $this->logger->debugWithUser('UserQueryResolver.resolveFriends started', $ctx->currentUserId);

        $result = $this->userService->getFriends($args);
        
        if ($result instanceof ErrorResponse) {
            return $result->response;
        }
        
        $this->logger->info('Friends list retrieved successfully', ['userCount' => count($result)]);
        
        return self::createSuccessResponse(
            count($result) > 0 ? 11102 : 21101,
            $result
        );
    }

    protected function resolveAllFriends(array $args, Context $ctx): ?array
    {
        $this->logger->debugWithUser('UserQueryResolver.resolveAllFriends started', $ctx->currentUserId);

        $result = $this->userService->getAllFriends($args);

        if ($result instanceof ErrorResponse) {
            return $result->response;
        }

        $this->logger->info('All friends list retrieved successfully', ['userCount' => count($result)]);
        
        return self::createSuccessResponse(
            count($result) > 0 ? 11102 : 21101,
            $result
        );
    }
}
