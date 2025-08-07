<?php
declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\Database\PostInfoMapper;
use Fawaz\Database\ReportsMapper;
use Fawaz\Database\CommentMapper;
use Fawaz\Utils\ReportTargetType;
use Psr\Log\LoggerInterface;
use Fawaz\Database\PostMapper;

class PostInfoService
{
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger, 
        protected PostInfoMapper $postInfoMapper, 
        protected CommentMapper $commentMapper, 
        protected ReportsMapper $reportMapper,
        protected PostMapper $postMapper
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

        $this->logger->info('PostInfoService.updatePostInfo started');

        try {
            $this->postInfoMapper->update($postInfo);
            return ['status' => 'success', 'ResponseCode' => 11509,];
        } catch (\Throwable $e) {
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

    //     $this->logger->info('PostInfoService.deletePostInfo started');

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
            // 'ResponseCode' => 11503,
            'ResponseCode' => 11514,

        ];
    }

    public function dislikePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
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

        $this->logger->info('PostInfoService.reportPost started');

        if (!self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        try {
            $post = $this->postMapper->loadById($postId);
            if (!$post) {
                $this->logger->error('PostInfoService: reportPost: Post not found');
                return $this->respondWithError(31510);
            }

            $postInfo = $this->postInfoMapper->loadById($postId);
            if ($postInfo === null) {
                $this->logger->error('PostInfoService: reportPost: Error while fetching comment data from db');
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
            $exists = $this->reportMapper->addReport(
                $this->currentUserId,
                ReportTargetType::POST, 
                $postId, 
                $contentHash
            );

            if ($exists === null) {
                $this->logger->error("PostInfoService: reportPost: Failed to add report");
                return $this->respondWithError(41505);
            }

            if ($exists === true) {
                $this->logger->warning("PostInfoService: reportPost: User tries to add duplicating report");
                return $this->respondWithError(31503);
            }

            $postInfo->setReports($postInfo->getReports() + 1);
            $this->postInfoMapper->update($postInfo);

            return [
                'status' => 'success',
                'ResponseCode' => 11505,    
            ];
        } catch (\Exception $e) {
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
            return $this->respondWithError(30209);
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
            return $this->respondWithError(30209);
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
            return $this->respondWithError(31510);
        }

        $results = $postinfo->getArrayCopy();

        return $results;
    }
}
