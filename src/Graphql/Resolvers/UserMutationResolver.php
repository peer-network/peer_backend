<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Resolvers;

use Fawaz\GraphQL\Context;
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
                'register' => fn (mixed $root, array $args, Context $ctx) => $this->createUser($args['input']),
                
                'updateUsername' => $this->withAuth(null, fn (mixed $root, array $args, Context $ctx) => $this->userService->setUsername($args)),
                'updateEmail' => $this->withAuth(null, fn (mixed $root, array $args, Context $ctx) => $this->userService->setEmail($args)),
                'updatePassword' => $this->withAuth(null, fn (mixed $root, array $args, Context $ctx) => $this->userService->setPassword($args)),
                'updateBio' => $this->withAuth(null, fn (mixed $root, array $args, Context $ctx) => $this->userInfoService->updateBio($args['biography'])),
                'updateProfileImage' => $this->withAuth(null, fn (mixed $root, array $args, Context $ctx) => $this->userInfoService->setProfilePicture($args['img'])),
                'toggleUserFollowStatus' => $this->withAuth(null, fn (mixed $root, array $args, Context $ctx) => $this->userInfoService->toggleUserFollow($args['userid'])),
                'toggleBlockUserStatus' => $this->withAuth(null, fn (mixed $root, array $args, Context $ctx) => $this->userInfoService->toggleUserBlock($args['userid'])),
                'deleteAccount' => $this->withAuth(null, fn (mixed $root, array $args, Context $ctx) => $this->userService->deleteAccount($args['password'])),
                'likeComment' => $this->withAuth(null, fn (mixed $root, array $args, Context $ctx) => $this->commentInfoService->likeComment($args['commentid'])),
                'reportComment' => $this->withAuth(null, fn (mixed $root, array $args, Context $ctx) => $this->commentInfoService->reportComment($args['commentid'])),
                'reportUser' => $this->withAuth(null, fn (mixed $root, array $args, Context $ctx) => $this->userInfoService->reportUser($args['userid'])),
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
}
