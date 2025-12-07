<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Resolvers;

use Fawaz\GraphQL\ResolverProvider;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\App\UserService;
use Fawaz\App\UserInfoService;
use Fawaz\App\CommentInfoService;
use Fawaz\GraphQL\Support\ResolverHelpers;
use Fawaz\Utils\ResponseHelper;

/**
 * Mutation resolvers for user-related actions.
 */
class UserMutationResolver implements ResolverProvider
{
    use ResolverHelpers;
    use ResponseHelper;

    public function __construct(
        private readonly PeerLoggerInterface $logger,
        private readonly UserService $userService,
        private readonly UserInfoService $userInfoService,
        private readonly CommentInfoService $commentInfoService,
    ) {
    }

    /** @return array<string, array<string, callable>> */
    public function getResolvers(): array
    {
        return [
            'Mutation' => [
                'updateUsername' => $this->withAuth(null, fn (mixed $root, array $args) => $this->userService->setUsername($args)),
                'updateEmail' => $this->withAuth(null, fn (mixed $root, array $args) => $this->userService->setEmail($args)),
                'updatePassword' => $this->withAuth(null, fn (mixed $root, array $args) => $this->userService->setPassword($args)),
                'updateBio' => $this->withAuth(null, fn (mixed $root, array $args) => $this->userInfoService->updateBio($args['biography'])),
                'updateProfileImage' => $this->withAuth(null, fn (mixed $root, array $args) => $this->userInfoService->setProfilePicture($args['img'])),
                'toggleUserFollowStatus' => $this->withAuth(null, fn (mixed $root, array $args) => $this->userInfoService->toggleUserFollow($args['userid'])),
                'toggleBlockUserStatus' => $this->withAuth(null, fn (mixed $root, array $args) => $this->userInfoService->toggleUserBlock($args['userid'])),
                'deleteAccount' => $this->withAuth(null, fn (mixed $root, array $args) => $this->userService->deleteAccount($args['password'])),
                'likeComment' => $this->withAuth(null, fn (mixed $root, array $args) => $this->commentInfoService->likeComment($args['commentid'])),
                'reportComment' => $this->withAuth(null, fn (mixed $root, array $args) => $this->commentInfoService->reportComment($args['commentid'])),
                'reportUser' => $this->withAuth(null, fn (mixed $root, array $args) => $this->userInfoService->reportUser($args['userid'])),
                'register' => $this->withAuth(null,fn (mixed $root, array $args) => $this->createUser($args['input'])),
                'listBlockedUsers' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [], 
                        fn (mixed $root, array $args) => $this->resolveBlocklist($args)
                    )
                ),
            ],
        ];
    }

    protected function createUser(array $args): ?array
    {
        $this->logger->debug('Query.createUser started');

        $response = $this->userService->createUser($args);
        if (isset($response['status']) && $response['status'] === 'error') {
            return $response;
        }

        if (!empty($response)) {
            return $response;
        }

        $this->logger->error('Query.createUser No data found');
        return $this::respondWithError(41105);
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