<?php

namespace Fawaz\App;

use Fawaz\Database\UserInfoMapper;
use Psr\Log\LoggerInterface;

class UserInfoService
{
    protected ?string $currentUserId = null;
    private FileUploader $fileUploader;

    public function __construct(
        protected LoggerInterface $logger,
        protected UserInfoMapper $userInfoMapper
    ) {
        $this->fileUploader = new FileUploader($this->logger);
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
            return $this->respondWithError('Invalid user ID');
        }

        if ($this->currentUserId === $followedUserId) {
            return $this->respondWithError('Cannot follow yourself');
        }

        $this->logger->info('UserInfoService.toggleUserFollow started');

        if (!$this->userInfoMapper->isUserExistById($this->currentUserId)) {
            return $this->respondWithError('Follower user not found');
        }

        if (!$this->userInfoMapper->isUserExistById($followedUserId)) {
            return $this->respondWithError('Followed user not found');
        }

        return $this->userInfoMapper->toggleUserFollow($this->currentUserId, $followedUserId);
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
				return $this->respondWithError('User not found');
			}

			// Toggle the privacy status
			$newIsPrivate = !$user->getIsPrivate();
			$user->setIsPrivate((int) $newIsPrivate);
			
			// Update the user information
			$updatedUser = $this->userInfoMapper->update($user);
			
			// Set response message based on new privacy status
			$responseMessage = $newIsPrivate ? 'Profile privacy set to private' : 'Profile privacy set to public';

			$this->logger->info('Profile privacy toggled', ['userId' => $this->currentUserId, 'newPrivacy' => $newIsPrivate]);

			return [
				'status' => 'success', 
				'ResponseCode' => $responseMessage, 
				//'affectedRows' => $updatedUser->getArrayCopy()
			];
		} catch (\Exception $e) {
			return $this->respondWithError('Failed to toggle profile privacy');
		}
	}

    public function updateBio(string $biography): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (trim($biography) === '' || strlen($biography) < 3 || strlen($biography) > 5000) {
            return $this->respondWithError('Biography must be between 3 and 5000 characters');
        }

        $this->logger->info('UserInfoService.updateBio started');

        try {
            $user = $this->userInfoMapper->loadById($this->currentUserId);
            if (!$user) {
                return $this->respondWithError('User not found');
            }

            $mediaPath = $this->fileUploader->handleFileUpload($biography, 'text', $this->currentUserId, true, 'userData');
            if (!$mediaPath) {
                return $this->respondWithError('Failed to upload media file');
            }

            $user->setBiography($mediaPath);
            $updatedUser = $this->userInfoMapper->updateUsers($user);
			$responseMessage = 'User biography updated successfully';

            $this->logger->info($responseMessage, ['userId' => $this->currentUserId]);

			return [
				'status' => 'success', 
				'ResponseCode' => $responseMessage, 
				//'affectedRows' => $updatedUser->getArrayCopy()
			];
        } catch (\Exception $e) {
            $this->logger->error('Error updating biography', ['exception' => $e]);
            return $this->respondWithError('Failed to update biography');
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
                return $this->respondWithError('User not found');
            }

            $mediaPath = $this->fileUploader->handleFileUpload($mediaFile, $contentType, $this->currentUserId, true, 'profile');
            if (!$mediaPath) {
                return $this->respondWithError('Failed to upload media file');
            }

            $user->setProfilePicture($mediaPath);
            $updatedUser = $this->userInfoMapper->updateUsers($user);
			$responseMessage = 'Profile picture updated successfully';

            $this->logger->info($responseMessage, ['userId' => $this->currentUserId]);

			return [
				'status' => 'success', 
				'ResponseCode' => $responseMessage, 
				//'affectedRows' => $updatedUser->getArrayCopy()
			];
        } catch (\Exception $e) {
            $this->logger->error('Error setting profile picture', ['exception' => $e]);
            return $this->respondWithError('Failed to update profile picture');
        }
    }
}
