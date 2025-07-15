<?php

namespace Fawaz\App;

use Fawaz\Database\UserInfoMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Database\ReportsMapper;
use Fawaz\Services\Base64FileHandler;
use Fawaz\Utils\ReportTargetType;
use Psr\Log\LoggerInterface;

class UserInfoService
{
    protected ?string $currentUserId = null;
    private Base64FileHandler $base64filehandler;

    public function __construct(
        protected LoggerInterface $logger,
        protected UserInfoMapper $userInfoMapper,
        protected UserMapper $userMapper,
        protected ReportsMapper $reportsMapper,
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

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    protected function createSuccessResponse(string $message, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): array 
    {
        $response = [
            'status' => 'success',
            'ResponseCode' => $message,
            'affectedRows' => $data,
        ];

        if ($countEnabled && is_array($data)) {
            if ($countKey !== null && isset($data[$countKey]) && is_array($data[$countKey])) {
                $response['counter'] = count($data[$countKey]);
            } else {
                $response['counter'] = count($data);
            }
        }

        return $response;
    }

    public function loadInfoById(): array|false
    {

        $this->logger->info('UserInfoService.loadLastId started');

        try {
            $results = $this->userInfoMapper->loadInfoById($this->currentUserId);

            if ($results !== false) {
                $affectedRows = $results->getArrayCopy();
                $this->logger->info("UserInfoService.loadInfoById found", ['affectedRows' => $affectedRows]);
                $success = [
                    'status' => 'success',
                    'ResponseCode' => 11002,
                    'affectedRows' => $affectedRows,
                ];
                return $success;
            }

            return $this->createSuccessResponse(21001);
        } catch (\Exception $e) {
            return $this->respondWithError(41001);
        }
    }

    public function toggleUserFollow(string $followedUserId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($followedUserId)) {
            return $this->respondWithError(30201);
        }

        if ($this->currentUserId === $followedUserId) {
            return $this->respondWithError(31102);
        }

        $this->logger->info('UserInfoService.toggleUserFollow started');

        if (!$this->userInfoMapper->isUserExistById($this->currentUserId)) {
            return $this->createSuccessResponse(21001);
        }

        if (!$this->userInfoMapper->isUserExistById($followedUserId)) {
            return $this->respondWithError(31003);
        }

        return $this->userInfoMapper->toggleUserFollow($this->currentUserId, $followedUserId);
    }

    public function toggleUserBlock(string $blockedUserId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($blockedUserId)) {
            return $this->respondWithError(30201);
        }

        if ($this->currentUserId === $blockedUserId) {
            return $this->respondWithError(31104);
        }

        $this->logger->info('UserInfoService.toggleUserBlock started');

        if (!$this->userInfoMapper->isUserExistById($this->currentUserId)) {
            return $this->createSuccessResponse(21001);
        }

        if (!$this->userInfoMapper->isUserExistById($blockedUserId)) {
            return $this->respondWithError(31106);
        }

        return $this->userInfoMapper->toggleUserBlock($this->currentUserId, $blockedUserId);
    }

    public function loadBlocklist(?array $args = []): array
    {
        $this->logger->info('UserInfoService.loadBlocklist started');

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
            return $this->respondWithError(41008);  
        }
    }

    public function toggleProfilePrivacy(): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('UserInfoService.toggleProfilePrivacy started');

        try {
            $user = $this->userInfoMapper->loadInfoById($this->currentUserId);
            if (!$user) {
                return $this->createSuccessResponse(21001);
            }

            $newIsPrivate = !$user->getIsPrivate();
            $user->setIsPrivate((int) $newIsPrivate);
            
            $updatedUser = $this->userInfoMapper->update($user);
            
            $responseMessage = $newIsPrivate ? 'Profile privacy set to private' : 'Profile privacy set to public';

            $this->logger->info('Profile privacy toggled', ['userId' => $this->currentUserId, 'newPrivacy' => $newIsPrivate]);

            return [
                'status' => 'success', 
                'ResponseCode' => $responseMessage, 
            ];
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to toggle profile privacy.');
        }
    }

    public function updateBio(string $biography): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (trim($biography) === '' || strlen($biography) < 3 || strlen($biography) > 5000) {
            return $this->respondWithError(30228);
        }

        $this->logger->info('UserInfoService.updateBio started');

        try {
            $user = $this->userInfoMapper->loadById($this->currentUserId);
            if (!$user) {
                return $this->createSuccessResponse(21001);
            }

                $mediaPath = $this->base64filehandler->handleFileUpload($biography, 'text', $this->currentUserId, 'userData');
                $this->logger->info('UserInfoService.updateBio biography', ['mediaPath' => $mediaPath]);

                if ($mediaPath === '') {
                    return $this->respondWithError(30251);
                }

                if (!empty($mediaPath['path'])) {
                    $mediaPathFile = $mediaPath['path'];
                } else {
                    return $this->respondWithError(40306);
                }

            $user->setBiography($mediaPathFile);
            $updatedUser = $this->userInfoMapper->updateUsers($user);
            $responseMessage = 11003;

            $this->logger->info($responseMessage, ['userId' => $this->currentUserId]);

            return [
                'status' => 'success', 
                'ResponseCode' => $responseMessage, 
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error updating biography', ['exception' => $e]);
            return $this->respondWithError(41002);
        }
    }

    public function setProfilePicture(string $mediaFile, string $contentType = 'image'): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (trim($mediaFile) === '') {
            return $this->respondWithError(31102);
        }

        $this->logger->info('UserInfoService.setProfilePicture started');

        try {
            $user = $this->userInfoMapper->loadById($this->currentUserId);
            if (!$user) {
                return $this->createSuccessResponse(21001);
            }

            if (!empty($mediaFile)) {
                $mediaPath = $this->base64filehandler->handleFileUpload($mediaFile, 'image', $this->currentUserId, 'profile');

                if ($mediaPath === '') {
                    return $this->respondWithError(30251);
                }

                if (!empty($mediaPath['path'])) {
                    $mediaPathFile = $mediaPath['path'];
                } else {
                    return $this->respondWithError(40306);
                }

            } else {
                return $this->respondWithError(40307);
            }

            $user->setProfilePicture($mediaPathFile);
            $updatedUser = $this->userInfoMapper->updateUsers($user);
            $responseMessage = 11004;

            $this->logger->info($responseMessage, ['userId' => $this->currentUserId]);

            return [
                'status' => 'success', 
                'ResponseCode' => $responseMessage, 
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error setting profile picture', ['exception' => $e]);
            return $this->respondWithError(41003);
        }
    }

    public function reportUser(string $reported_userid): array
    {
        $this->logger->info('UserInfoService.reportUser started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }   

        if (!self::isValidUUID($reported_userid)) {
            return $this->respondWithError(30201);
        }

        if ($this->currentUserId === $reported_userid) {
            $this->logger->error('UserInfoService.reportUser: Error: currentUserId == $reported_userid');
            return $this->respondWithError(31009); // you cant report on yourself
        }

        try {
            $user = $this->userMapper->loadById($reported_userid);

            if (!$user) {
                $this->logger->error('UserInfoService.reportUser: User not found');
                return $this->respondWithError(31007);
            }

            $userInfo = $this->userInfoMapper->loadInfoById($reported_userid);

            if (!$userInfo) {
                $this->logger->error('UserInfoService.reportUser: Error while fetching user data from db');
                return $this->respondWithError(41001); 
            }
        } catch (\Exception $e) {
            $this->logger->error('UserInfoService.reportUser: Error while fetching data for report generation ', ['exception' => $e]);
            return $this->respondWithError(00000); // 410xx - failed to report user
        }

        $contentHash = $user->hashValue();
        if (empty($contentHash)) {
            $this->logger->error('UserInfoService.reportUser: Error while generation content hash');
            return $this->respondWithError(00000); // 410xx - failed to report user
        }

        try {
            $exists = $this->reportsMapper->addReport(
                $this->currentUserId,
                ReportTargetType::USER, 
                $reported_userid, 
                $contentHash
            );

            if ($exists === null) {
                $this->logger->error("UserInfoService.reportUser: Failed to add report");
                return $this->respondWithError(00000); // 410xx - failed to report user
            }

            if ($exists === true) {
                $this->logger->error('UserInfoService.reportUser: User report already exists');
                return $this->respondWithError(31008); // report already exists
            }
            
            $userInfo->setReports($userInfo->getReports() + 1);
            $this->userInfoMapper->update($userInfo);

            return [
                'status' => 'success',
                'ResponseCode' => "11012", // added user report successfully
                'affectedRows' => $userInfo->getReports(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error while adding report to db or updating _info data', ['exception' => $e]);
            return $this->respondWithError(00000); // 410xx - failed to report user
        }
    }
}
