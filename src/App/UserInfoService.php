<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\Database\UserInfoMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Database\UserPreferencesMapper;
use Fawaz\Database\ReportsMapper;
use Fawaz\Services\Base64FileHandler;
use Fawaz\Services\ContentFiltering\HiddenContentFilterServiceImpl;
use Fawaz\Utils\ReportTargetType;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Utils\ResponseHelper;

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
        protected TransactionManager $transactionManager
    ) {
        $this->base64filehandler = new Base64FileHandler();
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized action attempted');
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
            return $this::respondWithError(41001);
        }
    }

    public function toggleUserFollow(string $followedUserId): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($followedUserId)) {
            return $this::respondWithError(30201);
        }

        if ($this->currentUserId === $followedUserId) {
            return $this::respondWithError(31102);
        }

        $this->logger->debug('UserInfoService.toggleUserFollow started');

        if (!$this->userInfoMapper->isUserExistById($this->currentUserId)) {
            return $this::createSuccessResponse(21001);
        }

        if (!$this->userInfoMapper->isUserExistById($followedUserId)) {
            return $this::respondWithError(31003);
        }

        $this->transactionManager->beginTransaction();

        $response = $this->userInfoMapper->toggleUserFollow($this->currentUserId, $followedUserId);

        if (isset($response['status']) && $response['status'] === 'error') {
            $this->logger->error('Error toggling user follow', ['error' => $response]);
            $this->transactionManager->rollback();
            return $response;
        }

        $this->transactionManager->commit();

        return $response;
    }

    public function toggleUserBlock(string $blockedUserId): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($blockedUserId)) {
            return $this::respondWithError(30201);
        }

        if ($this->currentUserId === $blockedUserId) {
            return $this::respondWithError(31104);
        }

        $this->logger->debug('UserInfoService.toggleUserBlock started');

        if (!$this->userInfoMapper->isUserExistById($this->currentUserId)) {
            return $this::createSuccessResponse(21001);
        }

        if (!$this->userInfoMapper->isUserExistById($blockedUserId)) {
            return $this::respondWithError(31106);
        }

        $this->transactionManager->beginTransaction();

        $response = $this->userInfoMapper->toggleUserBlock($this->currentUserId, $blockedUserId);

        if (isset($response['status']) && $response['status'] === 'error') {
            $this->logger->error('Error toggling user block', ['error' => $response]);
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

        try {
            $results = $this->userInfoMapper->getBlockRelations($this->currentUserId, $offset, $limit);
            if (isset($results['status']) && $results['status'] === 'error') {
                $this->logger->info("No blocked users found for user ID: {$this->currentUserId}");
                return $results;
            }

            $this->logger->info("UserInfoService.loadBlocklist found", ['results' => $results]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error("Error in UserInfoService.loadBlocklist", ['exception' => $e->getMessage()]);
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
            return $this::respondWithError(60501);
        }

        $bioConfig = ConstantsConfig::user()['BIOGRAPHY'];

        if (trim($biography) === '' || strlen($biography) < $bioConfig['MIN_LENGTH'] || strlen($biography) > $bioConfig['MAX_LENGTH']) {
            return $this::respondWithError(30228);
        }

        $this->logger->debug('UserInfoService.updateBio started');

        try {
            $this->transactionManager->beginTransaction();

            $user = $this->userInfoMapper->loadById($this->currentUserId);
            if (!$user) {
                return $this::createSuccessResponse(21001);
            }

            if (!empty($biography)) {
                $mediaPath = $this->base64filehandler->handleFileUpload($biography, 'text', $this->currentUserId, 'userData');
                $this->logger->info('UserInfoService.updateBio biography', ['mediaPath' => $mediaPath]);

                if (empty($mediaPath)) {
                    return $this::respondWithError(30251);
                }

                if (!empty($mediaPath['path'])) {
                    $mediaPathFile = $mediaPath['path'];
                } else {
                    return $this::respondWithError(40306);
                }
            } else {
                return $this::respondWithError(40307);
            }

            $user->setBiography($mediaPathFile);
            $updatedUser = $this->userInfoMapper->updateUsers($user);
            $responseMessage = "11003";

            $this->logger->info((string)$responseMessage, ['userId' => $this->currentUserId]);

            $this->transactionManager->commit();

            return [
                'status' => 'success',
                'ResponseCode' => $responseMessage,
            ];
        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Error updating biography', ['exception' => $e]);
            return $this::respondWithError(41002);
        }
    }

    public function setProfilePicture(string $mediaFile, string $contentType = 'image'): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (trim($mediaFile) === '') {
            return $this::respondWithError(31102);
        }

        $this->logger->debug('UserInfoService.setProfilePicture started');

        try {
            $user = $this->userInfoMapper->loadById($this->currentUserId);
            if (!$user) {
                return $this::createSuccessResponse(21001);
            }

            if (!empty($mediaFile)) {
                $mediaPath = $this->base64filehandler->handleFileUpload($mediaFile, 'image', $this->currentUserId, 'profile');

                if (empty($mediaPath)) {
                    return $this::respondWithError(30251);
                }

                if (!empty($mediaPath['path'])) {
                    $mediaPathFile = $mediaPath['path'];
                } else {
                    return $this::respondWithError(40306);
                }

            } else {
                return $this::respondWithError(40307);
            }
            $this->transactionManager->beginTransaction();

            $user->setProfilePicture($mediaPathFile);
            $updatedUser = $this->userInfoMapper->updateUsers($user);
            $responseMessage = "11004";

            $this->logger->info((string)$responseMessage, ['userId' => $this->currentUserId]);

            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => $responseMessage,
            ];
        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Error setting profile picture', ['exception' => $e]);
            return $this::respondWithError(41003);
        }
    }

    public function reportUser(string $reported_userid): array
    {
        $this->logger->debug('UserInfoService.reportUser started');

        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($reported_userid)) {
            return $this::respondWithError(30201);
        }

        if ($this->currentUserId === $reported_userid) {
            $this->logger->warning('UserInfoService.reportUser: Error: currentUserId == $reported_userid');
            return $this->respondWithError(31009); // you cant report on yourself
        }

        try {
            $user = $this->userMapper->loadById($reported_userid);

            if (!$user) {
                $this->logger->warning('UserInfoService.reportUser: User not found');
                return $this->respondWithError(31007);
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
                $this->logger->warning("UserInfoService.reportUser: User report already exists");
                return $this::respondWithError(32102); // This content has already been reviewed and restored by our team.
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
                $this->logger->warning('UserInfoService.reportUser: User report already exists');
                $this->transactionManager->rollback();
                return $this::respondWithError(31008); // report already exists
            }

            $userInfo->setReports($userInfo->getReports() + 1);
            $userInfo->setTotalReports($userInfo->getTotalReports() + 1);
            $this->userInfoMapper->update($userInfo);

            $this->transactionManager->commit();

            return $this::createSuccessResponse(11012);

        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Error while adding report to db or updating _info data', ['exception' => $e]);
            return $this::respondWithError(41015); // 410xx - failed to report user
        }
    }
}
