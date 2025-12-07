<?php

declare(strict_types=1);

namespace Fawaz\GraphQL\Resolvers;

use Fawaz\App\Interfaces\ProfileService;
use Fawaz\App\UserInfoService;
use Fawaz\GraphQL\ResolverProvider;
use Fawaz\GraphQL\Support\ResolverHelpers;
use Fawaz\App\Validation\RequestValidator;
use Fawaz\App\Validation\ValidatorErrors;
use Fawaz\Utils\ErrorResponse;
use Fawaz\Utils\PeerLoggerInterface;

/**
 * Query resolvers for User domain: listUsersV2, getProfile, getUserInfo.
 */
class UserQueryResolver implements ResolverProvider
{
    use ResolverHelpers;
    public function __construct(
        private readonly PeerLoggerInterface $logger,
        private readonly ProfileService $profileService,
        private readonly UserInfoService $userInfoService,
    ) {
    }

    /** @return array<string, array<string, callable>> */
    public function getResolvers(): array
    {
        return [
            'Query' => [
                'listUsersV2' => $this->withAuth(
                    null, 
                    fn (mixed $root, array $args) => $this->profileService->listUsers($args)
                ),
                'getUserInfo' => $this->withAuth(
                    null, 
                    fn (mixed $root, array $args) => $this->resolveUserInfo()
                ),
                'getProfile' => $this->withAuth(
                    null, 
                    $this->withValidation(
                        [], 
                        fn (mixed $root, array $validated) => $this->resolveProfile($validated)
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
}
