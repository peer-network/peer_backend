<?php

namespace Fawaz\App;

use Fawaz\Database\UserInfoMapper;
use Fawaz\Services\Base64FileHandler;
use Psr\Log\LoggerInterface;

class UserInfoService
{
    protected ?string $currentUserId = null;
    private Base64FileHandler $base64filehandler;

    public function __construct(
        protected LoggerInterface $logger,
        protected UserInfoMapper $userInfoMapper
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
                    'ResponseCode' => 'Userinfo data prepared successfully',
                    'affectedRows' => $affectedRows,
                ];
                return $success;
            }

            return $this->respondWithError('No data found for the user.');
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to retrieve user data.');
        }
    }

    public function toggleUserFollow(string $followedUserId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($followedUserId)) {
            return $this->respondWithError('Invalid user ID.');
        }

        if ($this->currentUserId === $followedUserId) {
            return $this->respondWithError('Cannot follow yourself.');
        }

        $this->logger->info('UserInfoService.toggleUserFollow started');

        if (!$this->userInfoMapper->isUserExistById($this->currentUserId)) {
            return $this->respondWithError('Follower user not found.');
        }

        if (!$this->userInfoMapper->isUserExistById($followedUserId)) {
            return $this->respondWithError('Followed user not found.');
        }

        return $this->userInfoMapper->toggleUserFollow($this->currentUserId, $followedUserId);
    }

    public function toggleUserBlock(string $blockedUserId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($blockedUserId)) {
            return $this->respondWithError('Invalid user ID.');
        }

        if ($this->currentUserId === $blockedUserId) {
            return $this->respondWithError('Cannot block yourself.');
        }

        $this->logger->info('UserInfoService.toggleUserBlock started');

        if (!$this->userInfoMapper->isUserExistById($this->currentUserId)) {
            return $this->respondWithError('Blocker user not found.');
        }

        if (!$this->userInfoMapper->isUserExistById($blockedUserId)) {
            return $this->respondWithError('Blocked user not found.');
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
            if (isset($response['status']) && $response['status'] === 'error') {
                $this->logger->info("No blocked users found for user ID: {$this->currentUserId}");
                return $response;
            }

            $this->logger->info("UserInfoService.loadBlocklist found", ['results' => $results]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error("Error in UserInfoService.loadBlocklist", ['exception' => $e->getMessage()]);
            return $this->respondWithError('Failed to retrieve user data.');  
        }
    }

    public function toggleProfilePrivacy(): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $this->logger->info('UserInfoService.toggleProfilePrivacy started');

        try {
            $user = $this->userInfoMapper->loadInfoById($this->currentUserId);
            if (!$user) {
                return $this->respondWithError('User not found.');
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
            return $this->respondWithError('Unauthorized');
        }

        if (trim($biography) === '' || strlen($biography) < 3 || strlen($biography) > 5000) {
            return $this->respondWithError('Biography must be between 3 and 5000 characters.');
        }

        $this->logger->info('UserInfoService.updateBio started');

        try {
            $user = $this->userInfoMapper->loadById($this->currentUserId);
            if (!$user) {
                return $this->respondWithError('User not found.');
            }

            if (!empty($biography)) {
                $mediaPath = $this->base64filehandler->handleFileUpload($biography, 'text', $this->currentUserId, 'userData');
                $this->logger->info('UserInfoService.updateBio biography', ['mediaPath' => $mediaPath]);

                if ($mediaPath === '') {
                    return $this->respondWithError('Biography upload failed.');
                }

                if (!empty($mediaPath['path'])) {
                    $mediaPathFile = $mediaPath['path'];
                } else {
                    return $this->respondWithError('Media path necessary for upload.');
                }

            } else {
                return $this->respondWithError('Media necessary for upload.');
            }

            $user->setBiography($mediaPathFile);
            $updatedUser = $this->userInfoMapper->updateUsers($user);
            $responseMessage = 'User biography updated successfully';

            $this->logger->info($responseMessage, ['userId' => $this->currentUserId]);

            return [
                'status' => 'success', 
                'ResponseCode' => $responseMessage, 
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error updating biography', ['exception' => $e]);
            return $this->respondWithError('Failed to update biography.');
        }
    }

    public function setProfilePicture(string $mediaFile, string $contentType = 'image'): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (trim($mediaFile) === '') {
            return $this->respondWithError('No media file provided');
        }

        $this->logger->info('UserInfoService.setProfilePicture started');

        try {
            $user = $this->userInfoMapper->loadById($this->currentUserId);
            if (!$user) {
                return $this->respondWithError('User not found.');
            }

            if (!empty($mediaFile)) {
                $mediaPath = $this->base64filehandler->handleFileUpload($mediaFile, 'image', $this->currentUserId, 'profile');

                if ($mediaPath === '') {
                    return $this->respondWithError('Profile upload failed.');
                }

                if (!empty($mediaPath['path'])) {
                    $mediaPathFile = $mediaPath['path'];
                } else {
                    return $this->respondWithError('Media path necessary for upload.');
                }

            } else {
                return $this->respondWithError('Media necessary for upload.');
            }

            $user->setProfilePicture($mediaPathFile);
            $updatedUser = $this->userInfoMapper->updateUsers($user);
            $responseMessage = 'Profile picture updated successfully';

            $this->logger->info($responseMessage, ['userId' => $this->currentUserId]);

            return [
                'status' => 'success', 
                'ResponseCode' => $responseMessage, 
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error setting profile picture', ['exception' => $e]);
            return $this->respondWithError('Failed to update profile picture');
        }
    }
}
