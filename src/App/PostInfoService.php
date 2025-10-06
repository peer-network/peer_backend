<?php

namespace Fawaz\App;

use Fawaz\Database\PostInfoMapper;
use Fawaz\Database\ReportsMapper;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Utils\ReportTargetType;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Database\PostMapper;

class PostInfoService
{
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger, 
        protected PostInfoMapper $postInfoMapper, 
        protected CommentMapper $commentMapper, 
        protected ReportsMapper $reportMapper,
        protected PostMapper $postMapper,
        protected TransactionManager $transactionManager
    ){}

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    protected function respondWithError(int $message): array
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

        $this->logger->debug('PostInfoService.updatePostInfo started');

        try {
            $this->transactionManager->beginTransaction();

            $this->postInfoMapper->update($postInfo);

            $this->transactionManager->commit();
            return ['status' => 'success', 'ResponseCode' => 11509,];
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            return $this->respondWithError(41509);
        }
    }

    protected function createSuccessResponse(int $message, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): array 
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

    // public function deletePostInfo(string $postId): array
    // {
    //     if (!$this->checkAuthentication()) {
    //         return $this->respondWithError(60501);
    //     }

    //     if (!self::isValidUUID($postId)) {
    //         return $this->respondWithError(30209);
    //     }

    //     $this->logger->debug('PostInfoService.deletePostInfo started');

    //     if ($this->postInfoMapper->delete($postId)) {
    //         return ['status' => 'success', 'ResponseCode' => 11510,];
    //     } else {
    //         return $this->respondWithError(41510);
    //     }
    // }

    public function likePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        $this->logger->debug('PostInfoService.likePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            return $this->respondWithError(31602);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError(31506);
        }

        try{
            $this->transactionManager->beginTransaction();
        
            $exists = $this->postInfoMapper->addUserActivity('likePost', $this->currentUserId, $postId);

            if (!$exists) {
                $this->transactionManager->rollback();
                return $this->respondWithError(31501);
            }

            $postInfo->setLikes($postInfo->getLikes() + 1);
            $this->postInfoMapper->update($postInfo);

            $this->transactionManager->commit();
            return [
                'status' => 'success',
                // 'ResponseCode' => 11503,
                'ResponseCode' => 11514,

            ];
        }catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('PostInfoService: likePost: Error while fetching post data', ['exception' => $e]);
            return $this->respondWithError(41505);
        }
    }

    public function dislikePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        $this->logger->debug('PostInfoService.dislikePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            return $this->respondWithError(31602);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError(31507);
        }

        try{
            $this->transactionManager->beginTransaction();

            $exists = $this->postInfoMapper->addUserActivity('dislikePost', $this->currentUserId, $postId);

            if (!$exists) {
                $this->transactionManager->rollback();
                return $this->respondWithError(31502);
            }

            $postInfo->setDislikes($postInfo->getDislikes() + 1);
            $this->postInfoMapper->update($postInfo);

            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => 11504,
            ];
         }catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('PostInfoService: dislikePost: Error while fetching post data', ['exception' => $e]);
            return $this->respondWithError(41505);
        }
    }

    public function reportPost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->debug('PostInfoService.reportPost started');

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        try {
            $post = $this->postMapper->loadById($postId);
            if (!$post) {
                $this->logger->warning('PostInfoService: reportPost: Post not found');
                return $this->respondWithError(31510);
            }

            $postInfo = $this->postInfoMapper->loadById($postId);
            if ($postInfo === null) {
                $this->logger->warning('PostInfoService: reportPost: Error while fetching comment data from db');
                return $this->respondWithError(31602);
            }
        } catch (\Exception $e) {
            $this->logger->error('PostInfoService: reportPost: Error while fetching data for report generation ', ['exception' => $e]);
            return $this->respondWithError(41505);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            $this->logger->warning("PostInfoService: reportPost: User tries to report on his own post");
            return $this->respondWithError(31508);
        }
        
        $contentHash = $post->hashValue();
        if (empty($contentHash)) {
            $this->logger->error('PostInfoService: reportPost: Failed to generate content hash of content');
            return $this->respondWithError(41505);
        }

        try {
            $this->transactionManager->beginTransaction();

            $exists = $this->reportMapper->addReport(
                $this->currentUserId,
                ReportTargetType::POST, 
                $postId, 
                $contentHash
            );

            if ($exists === null) {
                $this->transactionManager->rollback();
                $this->logger->error("PostInfoService: reportPost: Failed to add report");
                return $this->respondWithError(41505);
            }

            if ($exists === true) {
                $this->transactionManager->rollback();
                $this->logger->warning("PostInfoService: reportPost: User tries to add duplicating report");
                return $this->respondWithError(31503);
            }

            $postInfo->setReports($postInfo->getReports() + 1);
            $this->postInfoMapper->update($postInfo);

            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => 11505,    
            ];
        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('PostInfoService: reportPost: Error while adding report to db or updating _info data', ['exception' => $e]);
            return $this->respondWithError(41505);
        }
    }

    public function viewPost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        $this->logger->debug('PostInfoService.viewPost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            return $this->respondWithError(31602);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError(31509);
        }
        try{
            $this->transactionManager->beginTransaction();

            $exists = $this->postInfoMapper->addUserActivity('viewPost', $this->currentUserId, $postId);

            if (!$exists) {
                $this->transactionManager->rollback();
                return $this->respondWithError(31505);
            }

            $postInfo->setViews($postInfo->getViews() + 1);
            $this->postInfoMapper->update($postInfo);

            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => 11506,
            ];
        }catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('PostInfoService: viewPost: Error while fetching post data', ['exception' => $e]);
            return $this->respondWithError(41505);
        }
    }

    public function sharePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        $this->logger->debug('PostInfoService.sharePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            return $this->respondWithError(31602);
        }

        try{
            $this->transactionManager->beginTransaction();

            $exists = $this->postInfoMapper->addUserActivity('sharePost', $this->currentUserId, $postId);

            if (!$exists) {
                $this->transactionManager->rollback();
                return $this->respondWithError(31504);
            }

            $postInfo->setShares($postInfo->getShares() + 1);
            $this->postInfoMapper->update($postInfo);

            $this->transactionManager->commit();
            return [
                'status' => 'success',
                'ResponseCode' => 11507,
            ];
        }catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('PostInfoService: sharePost: Error while fetching post data', ['exception' => $e]);
            return $this->respondWithError(41505);
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

        $this->logger->debug('PostInfoService.toggleUserFollow started');

        if (!$this->postInfoMapper->isUserExistById($followedUserId)) {
            return $this->respondWithError(31105);
        }

        $this->transactionManager->beginTransaction();

        $response = $this->postInfoMapper->toggleUserFollow($this->currentUserId, $followedUserId);

        if (isset($response['status']) && $response['status'] === 'error') {
            $this->logger->error('PostInfoService.toggleUserFollow Error toggling user follow', ['error' => $response]);
            $this->transactionManager->rollback();
            return $response;
        }
        $this->transactionManager->commit();

        return $response;

    }

    public function savePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        $this->logger->debug('PostInfoService.savePost started');

        $this->transactionManager->beginTransaction();

        $response = $this->postInfoMapper->togglePostSaved($this->currentUserId, $postId);
        
        if (isset($response['status']) && $response['status'] === 'error') {
            $this->logger->error('PostInfoService.savePost Error save post', ['error' => $response]);
            $this->transactionManager->rollback();
            return $response;
        }
        $this->transactionManager->commit();

        return $response;

    }

    public function findPostInfo(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->debug("PostInfoService.findPostInfo started");

        $postinfo = $this->postInfoMapper->loadById($postId);
        if ($postinfo === null) {
            return $this->respondWithError(31510);
        }

        $results = $postinfo->getArrayCopy();

        return $results;
    }
}
