<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\Database\CommentInfoMapper;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\ReportsMapper;
use Fawaz\Database\ModerationMapper;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\IllegalContent\IllegalContentFilterSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\DeletedUserSpec;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\SystemUserSpec;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
use Fawaz\Utils\ReportTargetType;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;

class CommentInfoService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected CommentInfoMapper $commentInfoMapper,
        protected ReportsMapper $reportsMapper,
        protected CommentMapper $commentMapper,
        protected TransactionManager $transactionManager,
        protected InteractionsPermissionsMapper $interactionsPermissionsMapper,
        protected ModerationMapper $moderationMapper
    ) {
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
            $this->logger->error('CommentInfoService.checkAuthentication: Unauthorized access attempt');
            return false;
        }
        return true;
    }

    public function deleteCommentInfo(string $commentId): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('CommentInfoService.deleteCommentInfo: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            $this->logger->debug('CommentInfoService.deleteCommentInfo: Invalid commentId', ['commentId' => $commentId]);
            return $this::respondWithError(30201);
        }

        $this->logger->debug('CommentInfoService.deleteCommentInfo started');

        if ($this->commentMapper->delete($commentId)) {
            return ['status' => 'success', 'ResponseCode' => "11606"];
        } else {
            $this->logger->error('CommentInfoService.deleteCommentInfo: Failed to delete comment', ['commentId' => $commentId]);
            return $this::respondWithError(41603);
        }
    }

    public function countLikes(string $commentId): int|array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('CommentInfoService.countLikes: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            $this->logger->debug('CommentInfoService.countLikes: Invalid commentId', ['commentId' => $commentId]);
            return $this::respondWithError(30103);
        }

        $this->logger->debug('CommentInfoService.countLikes started');

        $commentInfo = $this->commentInfoMapper->loadById($commentId);

        if (!$commentInfo) {
            $this->logger->debug('CommentInfoService.countLikes: Comment info not found', ['commentId' => $commentId]);
            return $this::respondWithError(31601);
        }

        return $this->commentInfoMapper->countLikes($commentId);
    }

    public function likeComment(string $commentId): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('CommentInfoService.likeComment: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            $this->logger->debug('CommentInfoService.likeComment: Invalid commentId', ['commentId' => $commentId]);
            return $this::respondWithError(30201);
        }

        $this->logger->debug('CommentInfoService.likeComment started');

        $commentInfo = $this->commentInfoMapper->loadById($commentId);

        if (!$commentInfo) {
            $this->logger->debug('CommentInfoService.likeComment: Comment info not found', ['commentId' => $commentId]);
            return $this::respondWithError(31601);
        }

        if ($commentInfo->getOwnerId() === $this->currentUserId) {
            $this->logger->debug('CommentInfoService.likeComment: User cannot like own comment', ['commentId' => $commentId]);
            return $this::respondWithError(31606);
        }


        $contentFilterCase = ContentFilteringCases::searchById;

        $deletedUserSpec = new DeletedUserSpec(
            $contentFilterCase,
            ContentType::comment
        );
        $systemUserSpec = new SystemUserSpec(
            $contentFilterCase,
            ContentType::comment
        );

        $illegalContentSpec = new IllegalContentFilterSpec(
            $contentFilterCase,
            ContentType::comment
        );

        $specs = [
            $illegalContentSpec,
            $systemUserSpec,
            $deletedUserSpec
        ];

        if ($this->interactionsPermissionsMapper->isInteractionAllowed(
            $specs,
            $commentId
        ) === false) {
            $this->logger->debug('CommentInfoService.likeComment: Interaction not allowed', ['commentId' => $commentId]);
            return $this::respondWithError(31608, ['commentid' => $commentId]);
        }
        try {
            $this->transactionManager->beginTransaction();

            $exists = $this->commentInfoMapper->addUserActivity('likeComment', $this->currentUserId, $commentId);

            if (!$exists) {
                $this->logger->debug('CommentInfoService.likeComment: Failed to add user activity', ['commentId' => $commentId]);
                return $this::respondWithError(31604);
            }

            $commentInfo->setLikes($commentInfo->getLikes() + 1);
            $this->commentInfoMapper->update($commentInfo);

            $this->transactionManager->commit();

            return $this::createSuccessResponse(11603);
        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Error while fetching comment data', ['exception' => $e]);
            return $this::respondWithError(41601);
        }
    }

    public function reportComment(string $commentId): array
    {
        $this->logger->debug('CommentInfoService.reportComment started');

        if (!$this->checkAuthentication()) {
            $this->logger->error('CommentInfoService.reportComment: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            $this->logger->debug('CommentInfoService.reportComment: Invalid commentId', ['commentId' => $commentId]);
            return $this::respondWithError(30201);
        }

        try {
            $comment = $this->commentMapper->loadById($commentId);
            if (!$comment) {
                $this->logger->debug('CommentInfoService.reportComment: Comment not found', ['commentId' => $commentId]);
                return $this->respondWithError(31601);
            }

            if ($this->moderationMapper->wasContentRestored($commentId, 'comment')) {
                $this->logger->debug('CommentInfoService.reportComment: User tries to report a restored comment', ['commentId' => $commentId]);
                return $this->respondWithError(32104);
            }

            $commentInfo = $this->commentInfoMapper->loadById($commentId);

            if (!$commentInfo) {
                $this->logger->debug('CommentInfoService.reportComment: Error while fetching comment data from db', ['commentId' => $commentId]);
                return $this->respondWithError(31601);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error while fetching data for report generation ', ['exception' => $e]);
            return $this::respondWithError(41601);
        }

        if ($commentInfo->getOwnerId() === $this->currentUserId) {
            $this->logger->debug('CommentInfoService.reportComment: User tries to report own comment', ['commentId' => $commentId]);
            return $this::respondWithError(31607);
        }

        $contentHash = $comment->hashValue();
        if (empty($contentHash)) {
            $this->logger->error('Failed to generate content hash of content');
            return $this::respondWithError(41601);
        }

        try {
            // User Should NOT be possible to report the same
            if ($this->reportsMapper->isModerated($commentId, ReportTargetType::COMMENT->value)) {
                $this->logger->debug('CommentInfoService.reportComment: User tries to report a moderated comment', ['commentId' => $commentId]);
                return $this::respondWithError(32102); // This content has already been reviewed and moderated by our team.
            }

            $this->transactionManager->beginTransaction();

            $exists = $this->reportsMapper->addReport(
                $this->currentUserId,
                ReportTargetType::COMMENT,
                $commentId,
                $contentHash
            );

            if ($exists === null) {
                $this->logger->error("Failed to add report");
                return $this::respondWithError(41601);
            }

            if ($exists === true) {
                $this->logger->debug('CommentInfoService.reportComment: Comment report already exists', ['commentId' => $commentId]);
                return $this->respondWithError(31605);
            }

            $commentInfo->setReports($commentInfo->getActiveReports() + 1);
            $commentInfo->setTotalReports($commentInfo->getTotalReports() + 1);
            $this->commentInfoMapper->update($commentInfo);

            $this->transactionManager->commit();
            return $this::createSuccessResponse(11604);
        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Error while adding report to db or updating info data', ['exception' => $e]);
            return $this::respondWithError(41601);
        }
    }

    public function findCommentInfo(string $commentId): array|false
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('CommentInfoService.findCommentInfo: Authentication failed');
            return $this::respondWithError(60501);
        }

        $this->logger->debug("CommentInfoService.findCommentInfo started");

        $commentinfo = $this->commentInfoMapper->loadById($commentId);

        if (!$commentinfo) {
            $this->logger->debug('CommentInfoService.findCommentInfo: Comment info not found', ['commentId' => $commentId]);
            return $this::respondWithError(31601);
        }

        $results = $commentinfo->getArrayCopy();

        return $results;
    }
}
