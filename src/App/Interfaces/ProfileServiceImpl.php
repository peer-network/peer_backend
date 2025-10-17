<?php

namespace Fawaz\App\Interfaces;

use Fawaz\App\Profile;
use Fawaz\App\Specs\SpecTypes\ActiveUserSpec;
use Fawaz\App\Specs\SpecTypes\IllegalContentFilterSpec;
use Fawaz\App\Specs\SpecTypes\BasicUserSpec;
use Fawaz\App\Specs\SpecTypes\HiddenContentFilterSpec;
use Fawaz\App\Specs\SpecTypes\CurrentUserIsBlockedUserSpec;

use Fawaz\App\ValidationException;

use Fawaz\Utils\ErrorResponse;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;

use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\Replacers\ProfileReplacer;

use Fawaz\Database\Interfaces\ProfileRepository;

final class ProfileServiceImpl implements ProfileService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected ProfileRepository $profileRepository,
    ) {}

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    public function profile(array $args): Profile | ErrorResponse {
        $this->logger->info('ProfileService.Profile started');
        
        $userId = $args['userid'] ?? $this->currentUserId;
        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $activeUserSpec = new ActiveUserSpec($userId);
        $basicUserSpec = new BasicUserSpec($userId);
        $currentUserIsBlockedSpec = new CurrentUserIsBlockedUserSpec(
            $this->currentUserId,
            $userId
        );

        $usersHiddenContentFilterSpec = new HiddenContentFilterSpec(
            ContentFilteringStrategies::profile,
            $contentFilterBy,
            $this->currentUserId,
            $userId,
            ContentType::user,
            ContentType::user
        );

        $usersIllegalContentFilterSpec = new IllegalContentFilterSpec(
            ContentFilteringStrategies::profile,
            $contentFilterBy,
            $this->currentUserId,
            $userId,
            ContentType::user,
            ContentType::user
        );
        
        $specs = [
            $activeUserSpec,
            // $basicUserSpec,
            // $currentUserIsBlockedSpec,
            $usersHiddenContentFilterSpec,
            $usersIllegalContentFilterSpec
        ];

        try {
            $profileData = $this->profileRepository->fetchProfileData(
                $userId,
                $this->currentUserId,
                $specs
            );

            if (!$profileData) {
                $this->logger->warning('Query.resolveProfile User not found');
                return self::respondWithErrorObject(21001);
            }
            
            ProfileReplacer::placeholderProfile($profileData, $specs);

            $this->logger->debug("Fetched profile data", ['userid' => $profileData->getUserId()]);

            return $profileData;
        } catch (ValidationException $e) {
            $this->logger->error('Validation error: Failed to fetch profile data', [
                'userId' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return $this::respondWithErrorObject(31007);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch profile data', [
                'userId' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return $this::respondWithErrorObject(41007);
        }
    }
}
