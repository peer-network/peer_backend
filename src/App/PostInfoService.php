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
            return $this->respondWithError(60501);
        }

        $this->logger->info('PostInfoService.updatePostInfo started');

        try {
            $this->postInfoMapper->update($postInfo);
            return ['status' => 'success', 'ResponseCode' => 11509,];
        } catch (\Throwable $e) {
            return $this->respondWithError(41509);
        }
    }

    public function deletePostInfo(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(31501);
        }

        $this->logger->info('PostInfoService.deletePostInfo started');

        if ($this->postInfoMapper->delete($postId)) {
            return ['status' => 'success', 'ResponseCode' => 11510,];
        } else {
            return $this->respondWithError(41510);
        }
    }

    public function likePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(31501);
        }

        $this->logger->info('PostInfoService.likePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            return $this->respondWithError(31602);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError(31506);
        }

        $exists = $this->postInfoMapper->addUserActivity('likePost', $this->currentUserId, $postId);

        if (!$exists) {
            return $this->respondWithError(31501);
        }

        $postInfo->setLikes($postInfo->getLikes() + 1);
        $this->postInfoMapper->update($postInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 11503,
        ];
    }

    public function dislikePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(31501);
        }

        $this->logger->info('PostInfoService.dislikePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            return $this->respondWithError(31602);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError(31507);
        }

        $exists = $this->postInfoMapper->addUserActivity('dislikePost', $this->currentUserId, $postId);

        if (!$exists) {
            return $this->respondWithError(31502);
        }

        $postInfo->setDislikes($postInfo->getDislikes() + 1);
        $this->postInfoMapper->update($postInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 11504,
        ];
    }

    public function reportPost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(31501);
        }

        $this->logger->info('PostInfoService.reportPost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            return $this->respondWithError(31602);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError(31508);
        }

        $exists = $this->postInfoMapper->addUserActivity('reportPost', $this->currentUserId, $postId);

        if (!$exists) {
            return $this->respondWithError(31503);
        }

        $postInfo->setReports($postInfo->getReports() + 1);
        $this->postInfoMapper->update($postInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 11505,
        ];
    }

    public function viewPost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(31501);
        }

        $this->logger->info('PostInfoService.viewPost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            return $this->respondWithError(31602);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError(31509);
        }

        $exists = $this->postInfoMapper->addUserActivity('viewPost', $this->currentUserId, $postId);

        if (!$exists) {
            return $this->respondWithError(31505);
        }

        $postInfo->setViews($postInfo->getViews() + 1);
        $this->postInfoMapper->update($postInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 11506,
        ];
    }

    public function sharePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(31501);
        }

        $this->logger->info('PostInfoService.sharePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            return $this->respondWithError(31602);
        }

        $exists = $this->postInfoMapper->addUserActivity('sharePost', $this->currentUserId, $postId);

        if (!$exists) {
            return $this->respondWithError(31504);
        }

        $postInfo->setShares($postInfo->getShares() + 1);
        $this->postInfoMapper->update($postInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 11507,
        ];
    }

    public function toggleUserFollow(string $followedUserId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($followedUserId)) {
            return $this->respondWithError(30201);
        }

        $this->logger->info('PostInfoService.toggleUserFollow started');

        if (!$this->postInfoMapper->isUserExistById($followedUserId)) {
            return $this->respondWithError(31105);
        }

        return $this->postInfoMapper->toggleUserFollow($this->currentUserId, $followedUserId);
    }

    public function savePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(31501);
        }

        $this->logger->info('PostInfoService.savePost started');

        return $this->postInfoMapper->togglePostSaved($this->currentUserId, $postId);
    }

    public function findPostInfo(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info("PostInfoService.findPostInfo started");

        $postinfo = $this->postInfoMapper->loadById($postId);
        if ($postinfo === null) {
            return $this->respondWithError(21501);
        }

        $results = $postinfo->getArrayCopy();

        return $results;
    }
}
