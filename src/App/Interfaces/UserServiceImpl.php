<?php

namespace Fawaz\App\Interfaces;

use Fawaz\App\Profile;
use Fawaz\App\Specs\AndSpecification;
use Fawaz\App\Specs\ContentFilterSpec;
use Fawaz\App\Specs\SpecTypes\ContentFilterSpec as SpecTypesContentFilterSpec;
use Fawaz\App\Specs\SpecTypes\VerifiedUserSpec as SpecTypesVerifiedUserSpec;
use Fawaz\App\Specs\VerifiedUserSpec;
use Fawaz\Database\DailyFreeMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Database\UserPreferencesMapper;
use Fawaz\Database\PostMapper;
use Fawaz\Database\WalletMapper;
use Fawaz\Mail\UserWelcomeMail;
use Fawaz\Services\Base64FileHandler;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Strategies\ListPostsContentFilteringStrategy;
use Fawaz\Services\Mailer;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Strategies\GetProfileContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;

final class UserServiceImpl implements UserServiceInterface
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
        $postLimit = min(max((int)($args['postLimit'] ?? 4), 1), 10);
        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $this->logger->info('UserService.Profile started');

        if (!self::isValidUUID($userId)) {
            return self::respondWithError(30102);
        }
        if (!$this->userMapper->isUserExistById($userId)) {
            return self::respondWithError(31007);
        }

        $specs = [
            new SpecTypesContentFilterSpec(
                new GetProfileContentFilteringStrategy(),
                $contentFilterBy
            ),
            new SpecTypesVerifiedUserSpec(),
        ];

        try {
            $profileData = $this->userMapper->fetchProfileDataRaw($userId, $this->currentUserId, $specs);

            // $user_reports = (int)$data['user_reports'];
            // $user_dismiss_moderation_amount = (int)$data['user_count_content_moderation_dismissed'];

            // if ($contentFilterService->getContentFilterAction(
            //         ContentType::user,
            //         ContentType::user,
            //         $user_reports,$user_dismiss_moderation_amount,
            //         $currentUserId,$data['uid']
            //     ) == ContentFilteringAction::replaceWithPlaceholder) {
            //         $replacer = ContentReplacementPattern::flagged;
            //         $data['username'] = $replacer->username($data['username']);
            //         $data['img'] = $replacer->profilePicturePath($data['img']);
            //     }

            $posts = [];

            $contentTypes = ['image', 'video', 'audio', 'text'];

            foreach ($contentTypes as $type) {
                $profileData["{$type}posts"] = [];
            }

            return $this::createSuccessResponse(
                11008,
                $profileData,
                false
            );

        } catch (\Throwable $e) {
            return $this::createSuccessResponse(21001, []);
        }
    }
}
