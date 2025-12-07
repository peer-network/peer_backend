<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Resolvers;

use Fawaz\GraphQL\ResolverProvider;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\App\UserService;
use Fawaz\App\UserInfoService;
use Fawaz\App\CommentInfoService;
use Fawaz\GraphQL\Support\ResolverHelpers;

/**
 * Mutation resolvers for user-related actions.
 */
class UserMutationResolver implements ResolverProvider
{
    use ResolverHelpers;
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
            ],
        ];
    }
}
