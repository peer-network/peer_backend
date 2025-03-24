<?php

namespace Fawaz\App;

use Fawaz\Database\PostInfoMapper;
use Fawaz\Database\CommentMapper;
use Psr\Log\LoggerInterface;

class PostInfoService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected PostInfoMapper $postInfoMapper, protected CommentMapper $commentMapper)
    {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    protected function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    protected function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    public function updatePostInfo(PostInfo $postInfo): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $this->logger->info('PostInfoService.updatePostInfo started');

        try {
            $this->postInfoMapper->update($postInfo);
            return ['status' => 'success', 'ResponseCode' => 'Post info updated successfully',];
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to update post info');
        }
    }

    public function deletePostInfo(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError('Invalid postId');
        }

        $this->logger->info('PostInfoService.deletePostInfo started');

        if ($this->postInfoMapper->delete($postId)) {
            return ['status' => 'success', 'ResponseCode' => 'Post deleted successfully',];
        } else {
            return $this->respondWithError('Failed to delete post');
        }
    }

    public function likePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError('Invalid postId');
        }

        $this->logger->info('PostInfoService.likePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);

        if (!$postInfo) {
            return $this->respondWithError('Post not found');
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError('Post owner cannot like their own post');
        }

        $exists = $this->postInfoMapper->addUserActivity('likePost', $this->currentUserId, $postId);

        if (!$exists) {
            return $this->respondWithError('Already liked');
        }

        $postInfo->setLikes($postInfo->getLikes() + 1);
        $this->postInfoMapper->update($postInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 'Successfully liked',
        ];
    }

    public function dislikePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError('Invalid postId');
        }

        $this->logger->info('PostInfoService.dislikePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);

        if (!$postInfo) {
            return $this->respondWithError('Post not found');
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError('Post owner cannot dislike their own post');
        }

        $exists = $this->postInfoMapper->addUserActivity('dislikePost', $this->currentUserId, $postId);

        if (!$exists) {
            return $this->respondWithError('Already disliked');
        }

        $postInfo->setDislikes($postInfo->getDislikes() + 1);
        $this->postInfoMapper->update($postInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 'Successfully disliked',
        ];
    }

    public function reportPost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError('Invalid postId');
        }

        $this->logger->info('PostInfoService.reportPost started');

        $postInfo = $this->postInfoMapper->loadById($postId);

        if (!$postInfo) {
            return $this->respondWithError('Post not found');
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError('Post owner cannot report their own post');
        }

        $exists = $this->postInfoMapper->addUserActivity('reportPost', $this->currentUserId, $postId);

        if (!$exists) {
            return $this->respondWithError('Already report');
        }

        $postInfo->setReports($postInfo->getReports() + 1);
        $this->postInfoMapper->update($postInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 'Successfully report',
        ];
    }

    public function viewPost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError('Invalid postId');
        }

        $this->logger->info('PostInfoService.viewPost started');

        $postInfo = $this->postInfoMapper->loadById($postId);

        if (!$postInfo) {
            return $this->respondWithError('Post not found');
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError('Post owner cannot views their own post');
        }

        $exists = $this->postInfoMapper->addUserActivity('viewPost', $this->currentUserId, $postId);

        if (!$exists) {
            return $this->respondWithError('Already viewed');
        }

        $postInfo->setViews($postInfo->getViews() + 1);
        $this->postInfoMapper->update($postInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 'Successfully viewed',
        ];
    }

    public function sharePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError('Invalid postId');
        }

        $this->logger->info('PostInfoService.sharePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);

        if (!$postInfo) {
            return $this->respondWithError('Post not found');
        }

        $exists = $this->postInfoMapper->addUserActivity('sharePost', $this->currentUserId, $postId);

        if (!$exists) {
            return $this->respondWithError('Already shared');
        }

        $postInfo->setShares($postInfo->getShares() + 1);
        $this->postInfoMapper->update($postInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 'Successfully shared',
        ];
    }

    public function toggleUserFollow(string $followedUserId): array 
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($followedUserId)) {
            return $this->respondWithError('Invalid');
        }

        $this->logger->info('PostInfoService.toggleUserFollow started');

        if (!$this->postInfoMapper->isUserExistById($followedUserId)) {
            return $this->respondWithError('User not found');
        }

        return $this->postInfoMapper->toggleUserFollow($this->currentUserId, $followedUserId);
    }

    public function savePost(string $postId): array 
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError('Invalid postId');
        }

        $this->logger->info('PostInfoService.savePost started');

        return $this->postInfoMapper->togglePostSaved($this->currentUserId, $postId);
    }

    public function findPostInfo(string $postId): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $this->logger->info("PostInfoService.findPostInfo started");

        $postinfo = $this->postInfoMapper->loadById($postId);

        if (!$postinfo) {
            return $this->respondWithError('PostInfo not found.');
        }

        $results = $postinfo->getArrayCopy();

        return $results;
    }
}
