<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\Database\CommentInfoMapper;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\ReportsMapper;
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
        protected TransactionManager $transactionManager
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
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    public function deleteCommentInfo(string $commentId): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            return $this::respondWithError(30201);
        }

        $this->logger->debug('CommentInfoService.deleteCommentInfo started');

        if ($this->commentMapper->delete($commentId)) {
            return ['status' => 'success', 'ResponseCode' => "11606"];
        } else {
            return $this::respondWithError(41603);
        }
    }

    public function countLikes(string $commentId): int|array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            return $this::respondWithError(30103);
        }

        $this->logger->debug('CommentInfoService.countLikes started');

        $commentInfo = $this->commentInfoMapper->loadById($commentId);

        if (!$commentInfo) {
            return $this::respondWithError(31601);
        }

        return $this->commentInfoMapper->countLikes($commentId);
    }

    public function likeComment(string $commentId): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            return $this::respondWithError(30201);
        }

        $this->logger->debug('CommentInfoService.likeComment started');

        $commentInfo = $this->commentInfoMapper->loadById($commentId);

        if (!$commentInfo) {
            return $this::respondWithError(31601);
        }

        if ($commentInfo->getOwnerId() === $this->currentUserId) {
            return $this::respondWithError(31606);
        }

        try {
            $this->transactionManager->beginTransaction();

            $exists = $this->commentInfoMapper->addUserActivity('likeComment', $this->currentUserId, $commentId);

            if (!$exists) {
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
            return $this::respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            return $this::respondWithError(30201);
        }

        try {
            $comment = $this->commentMapper->loadById($commentId);
            if (!$comment) {
                $this->logger->warning('Comment not found');
                return $this->respondWithError(31601);
            }

            $commentInfo = $this->commentInfoMapper->loadById($commentId);

            if (!$commentInfo) {
                $this->logger->warning('Error while fetching comment data from db');
                return $this->respondWithError(31601);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error while fetching data for report generation ', ['exception' => $e]);
            return $this::respondWithError(41601);
        }

        if ($commentInfo->getOwnerId() === $this->currentUserId) {
            $this->logger->warning("User tries to report on his own comment");
            return $this::respondWithError(31607);
        }

        $contentHash = $comment->hashValue();
        if (empty($contentHash)) {
            $this->logger->error('Failed to generate content hash of content');
            return $this::respondWithError(41601);
        }

        try {
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
                $this->logger->warning('Post report already exists');
                return $this->respondWithError(31605);
            }

            $commentInfo->setReports($commentInfo->getReports() + 1);
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
            return $this::respondWithError(60501);
        }

        $this->logger->debug("CommentInfoService.findCommentInfo started");

        $commentinfo = $this->commentInfoMapper->loadById($commentId);

        if (!$commentinfo) {
            return $this::respondWithError(31601);
        }

        $results = $commentinfo->getArrayCopy();

        return $results;
    }
}
