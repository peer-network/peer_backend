<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\Database\PostInfoMapper;
use Fawaz\Database\ReportsMapper;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Utils\ReportTargetType;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Database\PostMapper;
use Fawaz\Database\ModerationMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\PeerShopSpec;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Utils\ResponseHelper;

class PostInfoService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected PostInfoMapper $postInfoMapper,
        protected CommentMapper $commentMapper,
        protected ReportsMapper $reportMapper,
        protected UserMapper $userMapper,
        protected PostMapper $postMapper,
        protected TransactionManager $transactionManager,
        protected InteractionsPermissionsMapper $interactionsPermissionsMapper,
        protected ModerationMapper $moderationMapper
    ) {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }



    protected function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('PostInfoService.checkAuthentication: Unauthorized access attempt');
            return false;
        }
        return true;
    }

    public function updatePostInfo(PostInfo $postInfo): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning('PostInfoService.updatePostInfo: Authentication failed');
            return $this::respondWithError(60501);
        }

        $this->logger->debug('PostInfoService.updatePostInfo started');

        try {
            $this->transactionManager->beginTransaction();

            $this->postInfoMapper->update($postInfo);

            $this->transactionManager->commit();
            return ['status' => 'success', 'ResponseCode' => "11509",];
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('PostInfoService.updatePostInfo: Failed to update post info', ['exception' => $e]);
            return $this::respondWithError(41509);
        }
    }


    public function likePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning('PostInfoService.likePost: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            $this->logger->debug('PostInfoService.likePost: Invalid postId', ['postId' => $postId]);
            return $this::respondWithError(30209);
        }

        $this->logger->debug('PostInfoService.likePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            $this->logger->debug('PostInfoService.likePost: Post info not found', ['postId' => $postId]);
            return $this::respondWithError(31602);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            $this->logger->debug('PostInfoService.likePost: User cannot like own post', ['postId' => $postId]);
            return $this::respondWithError(31506);
        }

        try {

            $exists = $this->postInfoMapper->addUserActivity('likePost', $this->currentUserId, $postId);

            if (!$exists) {
                $this->logger->debug('PostInfoService.likePost: Failed to add user activity', ['postId' => $postId]);
                return $this::respondWithError(31501);
            }

            $postInfo->setLikes($postInfo->getLikes() + 1);
            $this->postInfoMapper->update($postInfo);

            return [
                'status' => 'success',
                'ResponseCode' => "11514",

            ];
        } catch (\Exception $e) {
            $this->logger->error('PostInfoService: likePost: Error while fetching post data', ['exception' => $e]);
            return $this::respondWithError(41505);
        }
    }

    public function dislikePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning('PostInfoService.dislikePost: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            $this->logger->debug('PostInfoService.dislikePost: Invalid postId', ['postId' => $postId]);
            return $this::respondWithError(30209);
        }

        $this->logger->debug('PostInfoService.dislikePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            $this->logger->debug('PostInfoService.dislikePost: Post info not found', ['postId' => $postId]);
            return $this::respondWithError(31602);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            $this->logger->debug('PostInfoService.dislikePost: User cannot dislike own post', ['postId' => $postId]);
            return $this::respondWithError(31507);
        }

        try {

            $exists = $this->postInfoMapper->addUserActivity('dislikePost', $this->currentUserId, $postId);

            if (!$exists) {
                $this->logger->debug('PostInfoService.dislikePost: Failed to add user activity', ['postId' => $postId]);
                return $this::respondWithError(31502);
            }

            $postInfo->setDislikes($postInfo->getDislikes() + 1);
            $this->postInfoMapper->update($postInfo);

            return [
                'status' => 'success',
                'ResponseCode' => "11504",
            ];
        } catch (\Exception $e) {
            $this->logger->error('PostInfoService: dislikePost: Error while fetching post data', ['exception' => $e]);
            return $this::respondWithError(41505);
        }
    }

    public function reportPost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning('PostInfoService.reportPost: Authentication failed');
            return $this::respondWithError(60501);
        }

        $this->logger->debug('PostInfoService.reportPost started');

        if (!self::isValidUUID($postId)) {
            $this->logger->debug('PostInfoService.reportPost: Invalid postId', ['postId' => $postId]);
            return $this::respondWithError(30209);
        }

        try {
            $post = $this->postMapper->loadById($postId);
            if (!$post) {
                $this->logger->debug('PostInfoService.reportPost: Post not found', ['postId' => $postId]);
                return $this->respondWithError(31510);
            }

            $contentFilterCase = ContentFilteringCases::searchById;

            $peerShopUserSpec = new PeerShopSpec(
                $contentFilterCase,
                ContentType::post
            );
            $specs = [
                $peerShopUserSpec
            ];

            if ($this->interactionsPermissionsMapper->isInteractionAllowed(
                $specs,
                $postId
            ) === false) {
                $this->logger->debug('PostInfoService.reportPost: Interaction not allowed', ['postId' => $postId]);
                return $this::respondWithError(31107, ['postId' => $postId]);
            }

            if ($this->moderationMapper->wasContentRestored($postId, 'post')) {
                $this->logger->debug('PostInfoService.reportPost: User tries to report a restored post', ['postId' => $postId]);
                return $this->respondWithError(32104);
            }

            $postInfo = $this->postInfoMapper->loadById($postId);
            if ($postInfo === null) {
                $this->logger->error('PostInfoService.reportPost: Error while fetching post info from db', ['postId' => $postId]);
                return $this->respondWithError(responseCode: 31602);
            }
        } catch (\Exception $e) {
            $this->logger->error('PostInfoService: reportPost: Error while fetching data for report generation ', ['exception' => $e]);
            return $this::respondWithError(41505);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            $this->logger->debug('PostInfoService.reportPost: User tries to report on own post', ['postId' => $postId]);
            return $this::respondWithError(31508);
        }

        $contentHash = $post->hashValue();
        if (empty($contentHash)) {
            $this->logger->error('PostInfoService: reportPost: Failed to generate content hash of content');
            return $this::respondWithError(41505);
        }

        try {
            // Moderated items should not be reported again
            if ($this->reportMapper->isModerated($postId, ReportTargetType::POST->value)) {
                $this->logger->debug('PostInfoService.reportPost: User tries to report a moderated post', ['postId' => $postId]);
                return $this::respondWithError(32102); // This content has already been reviewed and moderated by our team.
            }

            $exists = $this->reportMapper->addReport(
                $this->currentUserId,
                ReportTargetType::POST,
                $postId,
                $contentHash
            );

            if ($exists === null) {
                $this->logger->error("PostInfoService: reportPost: Failed to add report");
                return $this::respondWithError(41505);
            }

            if ($exists === true) {
                $this->logger->debug('PostInfoService.reportPost: User tries to add duplicating report', ['postId' => $postId]);
                return $this::respondWithError(31503);
            }

            $postInfo->setReports($postInfo->getActiveReports() + 1);
            $postInfo->setTotalReports($postInfo->getTotalReports() + 1);
            $this->postInfoMapper->update($postInfo);

            return [
                'status' => 'success',
                'ResponseCode' => "11505",
            ];
        } catch (\Exception $e) {
            $this->logger->error('PostInfoService: reportPost: Error while adding report to db or updating _info data', ['exception' => $e]);
            return $this::respondWithError(41505);
        }
    }

    public function viewPost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning('PostInfoService.viewPost: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            $this->logger->debug('PostInfoService.viewPost: Invalid postId', ['postId' => $postId]);
            return $this::respondWithError(30209);
        }

        $this->logger->debug('PostInfoService.viewPost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            $this->logger->debug('PostInfoService.viewPost: Post info not found', ['postId' => $postId]);
            return $this::respondWithError(31602);
        }

        if ($postInfo->getOwnerId() === $this->currentUserId) {
            $this->logger->debug('PostInfoService.viewPost: User cannot view own post', ['postId' => $postId]);
            return $this::respondWithError(31509);
        }
        try {

            $exists = $this->postInfoMapper->addUserActivity('viewPost', $this->currentUserId, $postId);

            if (!$exists) {
                $this->logger->debug('PostInfoService.viewPost: Failed to add user activity', ['postId' => $postId]);
                return $this::respondWithError(31505);
            }

            $postInfo->setViews($postInfo->getViews() + 1);
            $this->postInfoMapper->update($postInfo);

            return [
                'status' => 'success',
                'ResponseCode' => "11506",
            ];
        } catch (\Exception $e) {
            $this->logger->error('PostInfoService: viewPost: Error while fetching post data', ['exception' => $e]);
            return $this::respondWithError(41505);
        }
    }

    public function sharePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning('PostInfoService.sharePost: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            $this->logger->debug('PostInfoService.sharePost: Invalid postId', ['postId' => $postId]);
            return $this::respondWithError(30209);
        }

        $this->logger->debug('PostInfoService.sharePost started');

        $postInfo = $this->postInfoMapper->loadById($postId);
        if ($postInfo === null) {
            $this->logger->debug('PostInfoService.sharePost: Post info not found', ['postId' => $postId]);
            return $this::respondWithError(31602);
        }

        try {

            $exists = $this->postInfoMapper->addUserActivity('sharePost', $this->currentUserId, $postId);

            if (!$exists) {
                $this->logger->debug('PostInfoService.sharePost: Failed to add user activity', ['postId' => $postId]);
                return $this::respondWithError(31504);
            }

            $postInfo->setShares($postInfo->getShares() + 1);
            $this->postInfoMapper->update($postInfo);

            return [
                'status' => 'success',
                'ResponseCode' => "11507",
            ];
        } catch (\Exception $e) {
            $this->logger->error('PostInfoService: sharePost: Error while fetching post data', ['exception' => $e]);
            return $this::respondWithError(41505);
        }
    }

    public function savePost(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning('PostInfoService.savePost: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($postId)) {
            $this->logger->debug('PostInfoService.savePost: Invalid postId', ['postId' => $postId]);
            return $this::respondWithError(30209);
        }

        $this->logger->debug('PostInfoService.savePost started');


        $response = $this->postInfoMapper->togglePostSaved($this->currentUserId, $postId);

        if (isset($response['status']) && $response['status'] === 'error') {
            $this->logger->error('PostInfoService.savePost Error save post', ['error' => $response]);
            return $response;
        }

        return $response;

    }

    public function findPostInfo(string $postId): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning('PostInfoService.findPostInfo: Authentication failed');
            return $this::respondWithError(60501);
        }

        $this->logger->debug("PostInfoService.findPostInfo started");

        $postinfo = $this->postInfoMapper->loadById($postId);
        if ($postinfo === null) {
            $this->logger->debug('PostInfoService.findPostInfo: Post info not found', ['postId' => $postId]);
            return $this::respondWithError(31510);
        }

        $results = $postinfo->getArrayCopy();

        return $results;
    }
}
