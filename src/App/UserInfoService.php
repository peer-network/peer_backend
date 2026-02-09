<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\Database\UserInfoMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Database\UserPreferencesMapper;
use Fawaz\Database\ReportsMapper;
use Fawaz\Database\ModerationMapper;
use Fawaz\Services\Base64FileHandler;
use Fawaz\Services\ContentFiltering\HiddenContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\Replaceables\ProfileReplaceable;
use Fawaz\Services\ContentFiltering\Replacers\ContentReplacer;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent\HiddenContentFilterSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\HiddenContent\NormalVisibilityStatusSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\IllegalContent\IllegalContentFilterSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\DeletedUserSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\SystemUserSpec;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Utils\ReportTargetType;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\PeerShopSpec;
use Fawaz\Utils\ResponseHelper;

use function grapheme_strlen;

class UserInfoService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;
    private Base64FileHandler $base64filehandler;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected UserInfoMapper $userInfoMapper,
        protected UserMapper $userMapper,
        protected UserPreferencesMapper $userPreferencesMapper,
        protected ReportsMapper $reportsMapper,
        protected TransactionManager $transactionManager,
        protected InteractionsPermissionsMapper $interactionsPermissionsMapper,
        protected ModerationMapper $moderationMapper
    ) {
        $this->base64filehandler = new Base64FileHandler();
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->error('UserInfoService.checkAuthentication: Unauthorized action attempted');
            return false;
        }
        return true;
    }

    public function loadInfoById(): array|false
    {

        $this->logger->debug('UserInfoService.loadLastId started');

        try {
            $results = $this->userInfoMapper->loadInfoById($this->currentUserId);

            $userPreferences = $this->userPreferencesMapper->loadPreferencesById($this->currentUserId);

            if ($results !== false && $userPreferences !== false) {
                $affectedRows = $results->getArrayCopy();
                $resultPreferences = $userPreferences->getArrayCopy();

                $contentFiltering = $resultPreferences['contentFilteringSeverityLevel'];

                $onboardings = $resultPreferences['onboardingsWereShown'] ?? [];
                if (!is_array($onboardings)) {
                    $onboardings = $onboardings ? (array) $onboardings : [];
                }

                $resultPreferences['onboardingsWereShown'] = $onboardings;

                $this->logger->info("UserInfoService.loadInfoById found", ['affectedRows' => $affectedRows]);

                $resultPreferences['contentFilteringSeverityLevel'] = HiddenContentFilterServiceImpl::getContentFilteringStringFromSeverityLevel($contentFiltering);

                $affectedRows['userPreferences'] = $resultPreferences;
                $affectedRows['onboardingsWereShown']   = $onboardings;

                return $this::createSuccessResponse(11002, $affectedRows, false);
            }

            return $this::createSuccessResponse(21001);
        } catch (\Exception $e) {
            $this->logger->error('UserInfoService.loadInfoById: Failed to load user info', ['exception' => $e]);
            return $this::respondWithError(41001);
        }
    }

    public function toggleUserFollow(string $followedUserId): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('UserInfoService.toggleUserFollow: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($followedUserId)) {
            $this->logger->error('UserInfoService.toggleUserFollow: Invalid followedUserId', ['followedUserId' => $followedUserId]);
            return $this::respondWithError(30201);
        }

        if ($this->currentUserId === $followedUserId) {
            $this->logger->error('UserInfoService.toggleUserFollow: Cannot follow self', ['followedUserId' => $followedUserId]);
            return $this::respondWithError(31102);
        }

        $this->logger->debug('UserInfoService.toggleUserFollow started');

        if (!$this->userInfoMapper->isUserExistById($this->currentUserId)) {
            return $this::createSuccessResponse(21001);
        }

        if (!$this->userInfoMapper->isUserExistById($followedUserId)) {
            $this->logger->error('UserInfoService.toggleUserFollow: Followed user not found', ['followedUserId' => $followedUserId]);
            return $this::respondWithError(31003);
        }

        $contentFilterCase = ContentFilteringCases::searchById;

        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::user
        );

        $specs = [
            $systemUserSpec
        ];

        if ($this->interactionsPermissionsMapper->isInteractionAllowed(
            $specs,
            $followedUserId
        ) === false) {
            $this->logger->error('UserInfoService.toggleUserFollow: Interaction not allowed', ['followedUserId' => $followedUserId]);
            return $this::respondWithError(31107, ['followedUserId' => $followedUserId]);
        }

        $this->transactionManager->beginTransaction();

        $response = $this->userInfoMapper->toggleUserFollow($this->currentUserId, $followedUserId);

        if (isset($response['status']) && $response['status'] === 'error') {
            $this->logger->error('UserInfoService.toggleUserFollow: Error toggling user follow', ['error' => $response]);
            $this->transactionManager->rollback();
            return $response;
        }

        $this->transactionManager->commit();

        return $response;
    }

    public function toggleUserBlock(string $blockedUserId): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('UserInfoService.toggleUserBlock: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($blockedUserId)) {
            $this->logger->error('UserInfoService.toggleUserBlock: Invalid blockedUserId', ['blockedUserId' => $blockedUserId]);
            return $this::respondWithError(30201);
        }

        if ($this->currentUserId === $blockedUserId) {
            $this->logger->error('UserInfoService.toggleUserBlock: Cannot block self', ['blockedUserId' => $blockedUserId]);
            return $this::respondWithError(31104);
        }

        $this->logger->debug('UserInfoService.toggleUserBlock started');

        if (!$this->userInfoMapper->isUserExistById($this->currentUserId)) {
            return $this::createSuccessResponse(21001);
        }

        if (!$this->userInfoMapper->isUserExistById($blockedUserId)) {
            $this->logger->error('UserInfoService.toggleUserBlock: Blocked user not found', ['blockedUserId' => $blockedUserId]);
            return $this::respondWithError(31106);
        }

        $contentFilterCase = ContentFilteringCases::searchById;

        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::user
        );
        $peerShopUserSpec = new PeerShopSpec(
            $contentFilterCase,
            ContentType::user
        );


        $specs = [
            $systemUserSpec,
            $peerShopUserSpec
        ];

        if ($this->interactionsPermissionsMapper->isInteractionAllowed(
            $specs,
            $blockedUserId
        ) === false) {
            $this->logger->error('UserInfoService.toggleUserBlock: Interaction not allowed', ['blockedUserId' => $blockedUserId]);
            return $this::respondWithError(31107, ['blockedUserId' => $blockedUserId]);
        }

        $this->transactionManager->beginTransaction();

        $response = $this->userInfoMapper->toggleUserBlock($this->currentUserId, $blockedUserId);

        if (isset($response['status']) && $response['status'] === 'error') {
            $this->logger->error('UserInfoService.toggleUserBlock: Error toggling user block', ['error' => $response]);
            $this->transactionManager->rollback();
            return $response;
        }
        $this->transactionManager->commit();

        return $response;
    }

    public function loadBlocklist(?array $args = []): array
    {
        $this->logger->debug('UserInfoService.loadBlocklist started');
        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $contentFilterCase = ContentFilteringCases::searchById;

        $deletedUserSpec = new DeletedUserSpec(
            ContentFilteringCases::searchById,
            ContentType::user
        );
        $systemUserSpec = new SystemUserSpec(
            ContentFilteringCases::searchById,
            ContentType::user
        );


        $usersHiddenContentFilterSpec = new HiddenContentFilterSpec(
            ContentFilteringCases::searchById,
            $contentFilterBy,
            ContentType::user,
            $this->currentUserId,
        );

        $illegalContentFilterSpec = new IllegalContentFilterSpec(
            ContentFilteringCases::searchById,
            ContentType::user
        );
        $normalVisibilityStatusSpec = new NormalVisibilityStatusSpec($contentFilterBy);

        $specs = [
            $illegalContentFilterSpec,
            $systemUserSpec,
            $deletedUserSpec,
            $usersHiddenContentFilterSpec,
            $normalVisibilityStatusSpec
        ];

        try {
            $lists = $this->userInfoMapper->getBlockRelations($this->currentUserId, $specs, $offset, $limit);

            $blockedBy = $lists['blockedBy'] ?? [];
            $iBlocked = $lists['iBlocked'] ?? [];

            foreach ($blockedBy as $profile) {
                if ($profile instanceof ProfileReplaceable) {
                    ContentReplacer::placeholderProfile($profile, $specs);
                }
            }
            foreach ($iBlocked as $profile) {
                if ($profile instanceof ProfileReplaceable) {
                    ContentReplacer::placeholderProfile($profile, $specs);
                }
            }

            $affected = [
                'blockedBy' => array_map(fn (Profile $p) => $p->getArrayCopy(), $blockedBy),
                'iBlocked' => array_map(fn (Profile $p) => $p->getArrayCopy(), $iBlocked),
            ];

            $counter = count($affected['blockedBy']) + count($affected['iBlocked']);

            $this->logger->info("UserInfoService.loadBlocklist found", ['counter' => $counter]);

            return [
                'status' => 'success',
                'counter' => $counter,
                'ResponseCode' => '11107',
                'affectedRows' => $affected,
            ];

        } catch (\Throwable $e) {
            $this->logger->error("UserInfoService.loadBlocklist: Error", ['exception' => $e->getMessage()]);
            return $this::respondWithError(41008);
        }
    }

    /* ----- unused function --------
        public function toggleProfilePrivacy(): array
        {
            if (!$this->checkAuthentication()) {
                return $this::respondWithError(60501);
            }

            $this->logger->debug('UserInfoService.toggleProfilePrivacy started');

            try {
                $this->transactionManager->beginTransaction();

                $user = $this->userInfoMapper->loadInfoById($this->currentUserId);
                if (!$user) {
                    return $this::createSuccessResponse(21001);
                }


                $newIsPrivate = !$user->getIsPrivate();
                $user->setIsPrivate((int) $newIsPrivate);

                $updatedUser = $this->userInfoMapper->update($user);

                $responseMessage = $newIsPrivate ? 'Profile privacy set to private' : 'Profile privacy set to public';

                $this->logger->info('Profile privacy toggled', ['userId' => $this->currentUserId, 'newPrivacy' => $newIsPrivate]);

                $this->transactionManager->commit();

                return [
                    'status' => 'success',
                    'ResponseCode' => $responseMessage,
                ];
            } catch (\Exception $e) {
                $this->transactionManager->rollback();
                return $this::respondWithError(00000);//'Failed to toggle profile privacy.'
            }
        }
    */

    public function updateBio(string $biography): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('UserInfoService.updateBio: Authentication failed');
            return $this::respondWithError(60501);
        }

        $illegalContentSpec = new IllegalContentFilterSpec(
            ContentFilteringCases::searchById,
            ContentType::user
        );

        $specs = [
            $illegalContentSpec
        ];

        if ($this->interactionsPermissionsMapper->isInteractionAllowed(
            $specs,
            $this->currentUserId
        ) === false) {
            $this->logger->error('UserInfoService.updateBio: Profile updates blocked due to moderation', ['userid' => $this->currentUserId]);
            return $this::respondWithError(31013);
        }

        $bioConfig = ConstantsConfig::user()['BIOGRAPHY'];

        if (trim($biography) === '' || grapheme_strlen($biography) < $bioConfig['MIN_LENGTH'] || grapheme_strlen($biography) > $bioConfig['MAX_LENGTH']) {
            $this->logger->error('UserInfoService.updateBio: Invalid biography length');
            return $this::respondWithError(30228);
        }

        $this->logger->debug('UserInfoService.updateBio started');

        try {
            $this->transactionManager->beginTransaction();

            $user = $this->userMapper->loadById($this->currentUserId);
            if (!$user) {
                return $this::createSuccessResponse(21001);
            }

            if (!empty($biography)) {
                $mediaPath = $this->base64filehandler->handleFileUpload($biography, 'text', $this->currentUserId, 'userData');
                $this->logger->info('UserInfoService.updateBio biography', ['mediaPath' => $mediaPath]);

                if (empty($mediaPath)) {
                    $this->logger->error('UserInfoService.updateBio: Biography upload failed');
                    return $this::respondWithError(30251);
                }

                if (!empty($mediaPath['path'])) {
                    $mediaPathFile = $mediaPath['path'];
                } else {
                    $this->logger->error('UserInfoService.updateBio: Biography path missing after upload');
                    return $this::respondWithError(40306);
                }
            } else {
                $this->logger->error('UserInfoService.updateBio: Biography is empty');
                return $this::respondWithError(40307);
            }

            $user->setBiography($mediaPathFile);
            $updatedUser = $this->userMapper->update($user);
            $responseMessage = "11003";

            $this->logger->info((string)$responseMessage, ['userId' => $this->currentUserId]);

            $this->transactionManager->commit();

            return [
                'status' => 'success',
                'ResponseCode' => $responseMessage,
            ];
        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('UserInfoService.updateBio: Error updating biography', ['exception' => $e]);
            return $this::respondWithError(41002);
        }
    }

    public function setProfilePicture(string $mediaFile, string $contentType = 'image'): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('UserInfoService.setProfilePicture: Authentication failed');
            return $this::respondWithError(60501);
        }

        $illegalContentSpec = new IllegalContentFilterSpec(
            ContentFilteringCases::searchById,
            ContentType::user
        );

        $specs = [
            $illegalContentSpec
        ];

        if ($this->interactionsPermissionsMapper->isInteractionAllowed(
            $specs,
            $this->currentUserId
        ) === false) {
            $this->logger->error('UserInfoService.setProfilePicture: Profile updates blocked due to moderation', ['userid' => $this->currentUserId]);
            return $this::respondWithError(31013);
        }

        if (trim($mediaFile) === '') {
            $this->logger->error('UserInfoService.setProfilePicture: Empty media file');
            return $this::respondWithError(31102);
        }

        $this->logger->debug('UserInfoService.setProfilePicture started');

        try {
            $user = $this->userMapper->loadById($this->currentUserId);
            if (!$user) {
                return $this::createSuccessResponse(21001);
            }

            if (!empty($mediaFile)) {
                $mediaPath = $this->base64filehandler->handleFileUpload($mediaFile, 'image', $this->currentUserId, 'profile');

                if (empty($mediaPath)) {
                    $this->logger->error('UserInfoService.setProfilePicture: Media upload failed');
                    return $this::respondWithError(30251);
                }

                if (!empty($mediaPath['path'])) {
                    $mediaPathFile = $mediaPath['path'];
                } else {
                    $this->logger->error('UserInfoService.setProfilePicture: Media path missing after upload');
                    return $this::respondWithError(40306);
                }

            } else {
                $this->logger->error('UserInfoService.setProfilePicture: Missing media file');
                return $this::respondWithError(40307);
            }
            $this->transactionManager->beginTransaction();

            $user->setProfilePicture($mediaPathFile);
            $updatedUser = $this->userMapper->update($user);
            $responseMessage = "11004";

            $this->logger->info((string)$responseMessage, ['userId' => $this->currentUserId]);

            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => $responseMessage,
            ];
        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('UserInfoService.setProfilePicture: Error setting profile picture', ['exception' => $e]);
            return $this::respondWithError(41003);
        }
    }

    public function reportUser(string $reported_userid): array
    {
        $this->logger->debug('UserInfoService.reportUser started');

        if (!$this->checkAuthentication()) {
            $this->logger->error('UserInfoService.reportUser: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($reported_userid)) {
            $this->logger->error('UserInfoService.reportUser: Invalid userId', ['reported_userid' => $reported_userid]);
            return $this::respondWithError(30201);
        }

        if ($this->currentUserId === $reported_userid) {
            $this->logger->error('UserInfoService.reportUser: Cannot report self', ['reported_userid' => $reported_userid]);
            return $this->respondWithError(31009); // you cant report on yourself
        }

        try {
            $user = $this->userMapper->loadById($reported_userid);

            if (!$user) {
                $this->logger->error('UserInfoService.reportUser: User not found', ['reported_userid' => $reported_userid]);
                return $this->respondWithError(31007);
            }

            $contentFilterCase = ContentFilteringCases::searchById;

            $peerShopUserSpec = new PeerShopSpec(
                $contentFilterCase,
                ContentType::user
            );

            $specs = [
                $peerShopUserSpec
            ];

            if ($this->interactionsPermissionsMapper->isInteractionAllowed(
                $specs,
                $reported_userid
            ) === false) {
                $this->logger->error('UserInfoService.reportUser: Interaction not allowed', ['reported_userid' => $reported_userid]);
                return $this::respondWithError(32201, ['userid' => $reported_userid]);
            }

            if ($this->moderationMapper->wasContentRestored($reported_userid, 'user')) {
                $this->logger->error('UserInfoService.reportUser: User tries to report a restored user', ['reported_userid' => $reported_userid]);
                return $this->respondWithError(32104);
            }

            $userInfo = $this->userInfoMapper->loadInfoById($reported_userid);

            if (!$userInfo) {
                $this->logger->error('UserInfoService.reportUser: Error while fetching user data from db');
                return $this::respondWithError(41001);
            }
        } catch (\Exception $e) {
            $this->logger->error('UserInfoService.reportUser: Error while fetching data for report generation ', ['exception' => $e]);
            return $this::respondWithError(41015); // 410xx - failed to report user
        }

        $contentHash = $user->hashValue();
        if (empty($contentHash)) {
            $this->logger->error('UserInfoService.reportUser: Error while generation content hash');
            return $this::respondWithError(41015); // 410xx - failed to report user
        }

        try {
            // Moderated items should not be reported again
            if ($this->reportsMapper->isModerated($reported_userid, ReportTargetType::USER->value)) {
                $this->logger->error('UserInfoService.reportUser: User report already exists', ['reported_userid' => $reported_userid]);
                return $this::respondWithError(32102); // This content has already been reviewed and noderated by our team.
            }

            $this->transactionManager->beginTransaction();

            $exists = $this->reportsMapper->addReport(
                $this->currentUserId,
                ReportTargetType::USER,
                $reported_userid,
                $contentHash
            );

            if ($exists === null) {
                $this->logger->error("UserInfoService.reportUser: Failed to add report");
                $this->transactionManager->rollback();
                return $this::respondWithError(41015); // 410xx - failed to report user
            }

            if ($exists === true) {
                $this->logger->error('UserInfoService.reportUser: User report already exists', ['reported_userid' => $reported_userid]);
                $this->transactionManager->rollback();
                return $this::respondWithError(31008); // report already exists
            }

            $userInfo->setReports($userInfo->getActiveReports() + 1);
            $userInfo->setTotalReports($userInfo->getTotalReports() + 1);
            $this->userInfoMapper->update($userInfo);

            $this->transactionManager->commit();

            return $this::createSuccessResponse(11012);

        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('UserInfoService.reportUser: Error while adding report to db or updating _info data', ['exception' => $e]);
            return $this::respondWithError(41015); // 410xx - failed to report user
        }
    }
}
