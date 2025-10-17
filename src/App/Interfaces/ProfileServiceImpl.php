<?php

namespace Fawaz\App\Interfaces;

use Fawaz\App\Specs\SpecTypes\ActiveUserSpec;
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
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use function PHPUnit\Framework\returnArgument;

final class ProfileServiceImpl implements ProfileService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger,
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

    public function Profile(?array $args = []): array {
        if (!self::checkAuthentication($this->currentUserId)) {
            return self::respondWithError(60501);
        }

        $userId = $args['userid'] ?? $this->currentUserId;
        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $this->logger->info('UserService.Profile started');

        if (!self::isValidUUID($userId)) {
            return self::respondWithError(30102);
        }
        if (!$this->userMapper->isUserExistById($userId)) {
            return self::respondWithError(31007);
        }

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

            
            // $profileData = $this->userMapper->fetchProfileData(
            //     $userId,
            //     $this->currentUserId,
            //     $userSpecs
            // );

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
                $replacer = ContentReplacementPattern::flagged;
                $profileData['username'] = $replacer->username($profileData['username']);
                $profileData['img'] = $replacer->profilePicturePath($profileData['img']);
            }

            $this->logger->debug("Fetched profile data", ['userid' => $profileData['uid']]);

            return $this::createSuccessResponse(
                11008,
                $profileData
            );
        } catch (ValidationException $e) {
            $this->logger->error('Failed to fetch profile data', [
                'userId' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return $this::respondWithError(41007);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch profile data', [
                'userId' => $userId,
                'exception' => $e->getMessage(),
            ]);
            return $this::respondWithError(41007);
        }
    }
}
