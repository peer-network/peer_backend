<?php

namespace Fawaz\App;

use Fawaz\Database\CommentInfoMapper;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\ReportsMapper;
use Fawaz\Utils\ReportTargetType;
use Psr\Log\LoggerInterface;

class CommentInfoService
{
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger, 
        protected CommentInfoMapper $commentInfoMapper, 
        protected ReportsMapper $reportsMapper,
        protected CommentMapper $commentMapper, 
    ){}

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
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
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            return $this->respondWithError(30201);
        }

        $this->logger->info('CommentInfoService.deleteCommentInfo started');

        if ($this->commentInfoMapper->delete($commentId)) {
            return ['status' => 'success', 'ResponseCode' => 11606];
        } else {
            return $this->respondWithError(41603);
        }
    }

    public function countLikes(string $commentId): int|array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            return $this->respondWithError(30103);
        }

        $this->logger->info('CommentInfoService.countLikes started');

        $commentInfo = $this->commentInfoMapper->loadById($commentId);

        if (!$commentInfo) {
            return $this->respondWithError(31601);
        }

        return $this->commentInfoMapper->countLikes($commentId);
    }

    public function likeComment(string $commentId): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            return $this->respondWithError(30201);
        }

        $this->logger->info('CommentInfoService.likeComment started');

        $commentInfo = $this->commentInfoMapper->loadById($commentId);

        if (!$commentInfo) {
            return $this->respondWithError(31601);
        }

        if ($commentInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError(31606);
        }

        $exists = $this->commentInfoMapper->addUserActivity('likeComment', $this->currentUserId, $commentId);

        if (!$exists) {
            return $this->respondWithError(31604);
        }

        $commentInfo->setLikes($commentInfo->getLikes() + 1);
        $this->commentInfoMapper->update($commentInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 11603,
            'affectedRows' => $commentInfo->getLikes(),
        ];
    }

    public function reportComment(string $commentId): array
    {
        $this->logger->info('CommentInfoService.reportComment started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            return $this->respondWithError(30201);
        }
        
        try {
            $comment = $this->commentMapper->loadById($commentId);
            if (!$comment) {
                $this->logger->error('Error while fetching comment data from db');
                return $this->respondWithError(00000);
            }

            $commentInfo = $this->commentInfoMapper->loadById($commentId);

            if (!$commentInfo) {
                $this->logger->error('Error while fetching comment data from db');
                return $this->respondWithError(31601);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error while fetching data for report generation ', ['exception' => $e]);
            return $this->respondWithError(00000);
        }
        
        
        $contentHash = $comment->hashValue();
        if (empty($contentHash)) {
            return $this->respondWithError(00000);
        }

        try {
            $exists = $this->reportsMapper->addReport(
                $this->currentUserId,
                ReportTargetType::COMMENT, 
                $commentId,
                $contentHash
            );

            if (!$exists) {
                return $this->respondWithError(31605);
            }

            $commentInfo->setReports($commentInfo->getReports() + 1);
            $this->commentInfoMapper->update($commentInfo);

            return [
                'status' => 'success',
                'ResponseCode' => 11604,
                'affectedRows' => $commentInfo->getReports(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error while adding report to db or updating _info data', ['exception' => $e]);
            return $this->respondWithError(00000);
        }
    }

    public function findCommentInfo(string $commentId): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info("CommentInfoService.findCommentInfo started");

        $commentinfo = $this->commentInfoMapper->loadById($commentId);

        if (!$commentinfo) {
            return $this->respondWithError(31601);
        }

        $results = $commentinfo->getArrayCopy();

        return $results;
    }
}
