<?php

namespace Fawaz\App\Interfaces;

use Fawaz\App\Profile;
use Fawaz\App\View\ProfileView;
use Fawaz\App\Specs\SpecTypes\BasicUserSpec;
use Fawaz\App\Specs\SpecTypes\HiddenContentFilterSpec;
use Fawaz\App\Specs\SpecTypes\CurrentUserIsBlockedUserSpec;
use Fawaz\App\ValidationException;
use Fawaz\Database\DailyFreeMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Database\UserPreferencesMapper;
use Fawaz\Database\PostMapper;
use Fawaz\Database\WalletMapper;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringStrategies;
use Fawaz\Services\Mailer;
use Fawaz\Utils\ErrorResponse;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Services\ContentFiltering\Replacers\ProfileReplacer;

final class ProfileServiceImpl implements ProfileService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected DailyFreeMapper $dailyFreeMapper,
        protected UserMapper $userMapper,
        protected UserPreferencesMapper $userPreferencesMapper,
        protected PostMapper $postMapper,
        protected WalletMapper $walletMapper,
		protected Mailer $mailer,
        protected TransactionManager $transactionManager
    ) {}

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    public function profile(array $args): Profile|ErrorResponse {
        $this->logger->info('UserService.Profile started');
        
        $userId = $args['userid'] ?? $this->currentUserId;
        $contentFilterBy = $args['contentFilterBy'] ?? null;

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
        
        $userSpecs = [
            $basicUserSpec,
            $currentUserIsBlockedSpec,
            $usersHiddenContentFilterSpec
        ];

        try {
            $profileData = $this->userMapper->fetchProfileData(
                $userId,
                $this->currentUserId,
                $userSpecs
            );

            if (!$profileData) {
                $this->logger->warning('Query.resolveProfile User not found');
                return self::respondWithErrorObject(21001);
            }

            $contentFilterService = new ContentFilterServiceImpl(
                ContentFilteringStrategies::profile,
                null,
                $contentFilterBy
            );
            
            if ($contentFilterService->getContentFilterAction(
                    ContentType::user,
                    ContentType::user,
                    $profileData->getReports(),
                    $this->currentUserId,
                    $profileData->getUserId()
            ) == ContentFilteringAction::replaceWithPlaceholder) {
                $pattern = ContentReplacementPattern::hidden;
                $profileData = ProfileReplacer::replaceProfile($profileData, $pattern);
            }

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
