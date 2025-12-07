<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Resolvers;

use Fawaz\App\Interfaces\ProfileService;
use Fawaz\App\Role;
use Fawaz\App\UserInfoService;
use Fawaz\App\UserService;
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
                    [Role::ADMIN],
                    $this->withValidation(
                        [],
                        fn (
                            mixed $root, array $validated, Context $ctx
                        ) => $this->profileService->searchUser($validated, $ctx)
                    )
                ),
                'listUsersAdminV2' => $this->withAuth(
                    [Role::ADMIN],
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

    public function resolveUserInfo(Context $ctx): array
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

    public function resolveProfile(array $args, Context $ctx): array
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

    public function resolveReferralInfo(array $args, Context $ctx): array
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

    public function resolveFollows(array $args, Context $ctx): array
    {
        $this->logger->debugWithUser('UserQueryResolver.resolveFollows started', $ctx->currentUserId);

        $result = $this->userService->follows($args);

        if ($result instanceof ErrorResponse) {
            return $result->response;
        }

        $followersCount = count($result['followers'] ?? []);
        $followingCount = count($result['following'] ?? []);
        $result['all'] = array_merge($result['followers'] ?? [], $result['following'] ?? []);
        $total = $followersCount + $followingCount;

        $this->logger->info(
            'Follow relations retrieved',
            [
                'followers' => $followersCount,
                'following' => $followingCount,
                'counter' => $total,
            ]
        );

        return $this->createSuccessResponse(
            11101,
            $result,
            true,
            'all'
        );
    }
    public function resolveBlocklist(array $args, Context $ctx): array
    {
        $this->logger->debugWithUser('UserQueryResolver.resolveBlocklist started', $ctx->currentUserId);

        $result = $this->userInfoService->loadBlocklist($args);

        if ($result instanceof ErrorResponse) {
            return $result->response;
        }

        $blockedByCount = count($result['blockedBy'] ?? []);
        $iBlockedCount = count($result['iBlocked'] ?? []);
        $result['blocked'] = array_merge($result['blockedBy'] ?? [], $result['iBlocked'] ?? []);
        $total = $blockedByCount + $iBlockedCount;

        $this->logger->info(
            'Blocklist retrieved successfully',
            [
                'blockedBy' => $blockedByCount,
                'iBlocked' => $iBlockedCount,
                'counter' => $total,
            ]
        );
        
        return $this->createSuccessResponse(
            11107,
            $result,
            true,
            'blocked'
        );
    }

    public function resolveFriends(array $args, Context $ctx): array
    {
        $this->logger->debugWithUser('UserQueryResolver.resolveFriends started', $ctx->currentUserId);

        $result = $this->userService->getFriends($args);
        
        if ($result instanceof ErrorResponse) {
            return $result->response;
        }
        
        $this->logger->info('Friends list retrieved successfully', ['userCount' => count($result)]);
        
        return $this->createSuccessResponse(
            count($result) > 0 ? 11102 : 21101,
            $result
        );
    }

    public function resolveAllFriends(array $args, Context $ctx): array
    {
        $this->logger->debugWithUser('UserQueryResolver.resolveAllFriends started', $ctx->currentUserId);

        $result = $this->userService->getAllFriends($args);

        if ($result instanceof ErrorResponse) {
            return $result->response;
        }

        $this->logger->info('All friends list retrieved successfully', ['userCount' => count($result)]);
        
        return $this->createSuccessResponse(
            count($result) > 0 ? 11102 : 21101,
            $result
        );
    }
}
