<?php

namespace Fawaz\App;

use Fawaz\Database\CommentInfoMapper;
use Psr\Log\LoggerInterface;

class CommentInfoService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected CommentInfoMapper $commentInfoMapper)
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
            return $this->respondWithError(20201);
        }

        $this->logger->info('CommentInfoService.deleteCommentInfo started');

        if ($this->commentInfoMapper->delete($commentId)) {
            return ['status' => 'success', 'ResponseCode' => 11606];
        } else {
            return $this->respondWithError(41603);
        }
    }

    public function countLikes(string $commentId): int
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
            return $this->respondWithError(20201);
        }

        $this->logger->info('CommentInfoService.likeComment started');

        $commentInfo = $this->commentInfoMapper->loadById($commentId);

        if (!$commentInfo) {
            return $this->respondWithError(31601);
        }

        if ($commentInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError(21602);
        }

        $exists = $this->commentInfoMapper->addUserActivity('likeComment', $this->currentUserId, $commentId);

        if (!$exists) {
            return $this->respondWithError(21603);
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
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (!self::isValidUUID($commentId)) {
            return $this->respondWithError(20201);
        }

        $this->logger->info('CommentInfoService.reportComment started');

        $commentInfo = $this->commentInfoMapper->loadById($commentId);

        if (!$commentInfo) {
            return $this->respondWithError(31601);
        }

        if ($commentInfo->getOwnerId() === $this->currentUserId) {
            return $this->respondWithError(21604);
        }

        $exists = $this->commentInfoMapper->addUserActivity('reportComment', $this->currentUserId, $commentId);

        if (!$exists) {
            return $this->respondWithError(21605);
        }

        $commentInfo->setReports($commentInfo->getReports() + 1);
        $this->commentInfoMapper->update($commentInfo);

        return [
            'status' => 'success',
            'ResponseCode' => 11604,
            'affectedRows' => $commentInfo->getReports(),
        ];
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
