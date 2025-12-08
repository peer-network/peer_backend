<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Resolvers;

use Fawaz\App\Validation\RequestValidator;
use Fawaz\App\Validation\ValidatorErrors;
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
                'register' => fn (mixed $root, array $args, Context $ctx) => $this->createUser($args),

                'updateUsername' => $this->withAuth(
                    null,
                    $this->withValidation(
                        ['username','password'],
                        fn (mixed $root, array $validated, Context $ctx) => $this->userService->setUsername($validated)
                    )
                ),
                'updateEmail' => $this->withAuth(
                    null,
                    $this->withValidation(
                        ['email','password'],
                        fn (mixed $root, array $validated, Context $ctx) => $this->userService->setEmail($validated)
                    )
                ),
                'updatePassword' => $this->withAuth(
                    null,
                    $this->withValidation(
                        ['password','expassword'],
                        fn (mixed $root, array $validated, Context $ctx) => $this->userService->setPassword($validated)
                    )
                ),
                'updateBio' => $this->withAuth(
                    null,
                    $this->withValidation(
                        ['biography'],
                        fn (mixed $root, array $validated, Context $ctx) => $this->userInfoService->updateBio($validated['biography'])
                    )
                ),
                'updateProfileImage' => $this->withAuth(
                    null,
                    $this->withValidation(
                        [],
                        fn (mixed $root, array $validated, Context $ctx) => $this->userInfoService->setProfilePicture($validated['img'])
                    )
                ),
                'toggleUserFollowStatus' => $this->withAuth(
                    null,
                    $this->withValidation(
                        ['userid'],
                        fn (mixed $root, array $validated, Context $ctx) => $this->userInfoService->toggleUserFollow($validated['userid'])
                    )
                ),
                'toggleBlockUserStatus' => $this->withAuth(
                    null,
                    $this->withValidation(
                        ['userid'],
                        fn (mixed $root, array $validated, Context $ctx) => $this->userInfoService->toggleUserBlock($validated['userid'])
                    )
                ),
                'deleteAccount' => $this->withAuth(
                    null,
                    $this->withValidation(
                        ['password'],
                        fn (mixed $root, array $validated, Context $ctx) => $this->userService->deleteAccount($validated['password'])
                    )
                ),
                'likeComment' => $this->withAuth(
                    null,
                    $this->withValidation(
                        ['commentid'],
                        fn (mixed $root, array $validated, Context $ctx) => $this->commentInfoService->likeComment($validated['commentid'])
                    )
                ),
                'reportComment' => $this->withAuth(
                    null,
                    $this->withValidation(
                        ['commentid'],
                        fn (mixed $root, array $validated, Context $ctx) => $this->commentInfoService->reportComment($validated['commentid'])
                    )
                ),
                'reportUser' => $this->withAuth(
                    null,
                    $this->withValidation(
                        ['userid'],
                        fn (mixed $root, array $validated, Context $ctx) => $this->userInfoService->reportUser($validated['userid'])
                    )
                ),
            ],
        ];
    }

    protected function createUser(array $args): ?array
    {
        $this->logger->debug('Query.createUser started');

        $validated = RequestValidator::validate(
            $args['input'], 
            ['username', 'email', 'password']
        );

        if ($validated instanceof ValidatorErrors) {
            return [
                'status' => 'error',
                'ResponseCode' => $validated->errors[0] ?? 30101,
            ];
        };
        $response = $this->userService->createUser($validated);
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
