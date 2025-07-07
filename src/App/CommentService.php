<?php

namespace Fawaz\App;

use Fawaz\App\Comment;
use Fawaz\App\CommentInfo;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\CommentInfoMapper;
use Fawaz\Database\PostInfoMapper;
use Fawaz\Database\UserMapper;
use Psr\Log\LoggerInterface;

class CommentService
{
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger,
        protected CommentMapper $commentMapper,
        protected CommentInfoMapper $commentInfoMapper,
        protected PostInfoMapper $postInfoMapper,
        protected UserMapper $userMapper
    ) {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function generateUUID(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/', $uuid) === 1;
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

    private function validateUUID(string $id): bool
    {
        return self::isValidUUID($id);
    }

    private function validateRequiredFields(array $args, array $requiredFields): array
    {
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                return $this->respondWithError(30265);
            }
        }
        return [];
    }

    public function createComment(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        if (empty($args)) {
            return $this->respondWithError(30101);
        }

        $this->logger->info('CommentService.createComment started');

        $requiredFields = ['content', 'postid'];
        $validationErrors = $this->validateRequiredFields($args, $requiredFields);
        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $content = trim($args['content']);
        $postId = trim($args['postid']);
        $parentId = isset($args['parentid']) ? trim($args['parentid']) : null;
        
        if (!$this->validateUUID($postId)) {
            return $this->respondWithError(30209, ['postid' => $postId]);
        }

        if ($parentId !== null && !$this->validateUUID($parentId)) {
			return $this->respondWithError(31603, ['parentId' => $parentId]);
        }

        if ($content === '') {
            return $this->respondWithError(30101);
        }

        if ($parentId !== null) {
            if (!$this->commentMapper->isParentTopLevel($parentId)) {
                return $this->respondWithError(41604);
            }
        }

        try {
            $commentId = $this->generateUUID();
            if (empty($commentId)) {
                $this->logger->critical('Failed to generate comment ID');
                return $this->respondWithError(41607);
            }

            $commentData = [
                'commentid' => $commentId,
                'userid' => $this->currentUserId,
                'postid' => $postId,
                'parentid' => $parentId,
                'content' => $content,
            ];

            // Post speichern
            try {
                $comment = new Comment($commentData);
            } catch (\Throwable $e) {
                return $this->respondWithError($e->getMessage());
            }

            $result = $this->commentMapper->insert($comment);

            if (!$result) {
                return $this->respondWithError(41602);
            }

            $postInfo = $this->postInfoMapper->loadById($postId);
            if (!$postInfo) {
                return $this->respondWithError(31602);
            }

            $postInfo->setComments($postInfo->getComments() + 1);
            $this->postInfoMapper->update($postInfo);

            $commentInfoData = [
                'commentid' => $commentId,
                'userid' => $this->currentUserId,
                'likes' => 0,
                'reports' => 0,
                'comments' => 0,
            ];
            $commentInfo = new CommentInfo($commentInfoData);
            $this->commentInfoMapper->insert($commentInfo);

            if (!empty($parentId)) {
                $parentCommentInfo = $this->commentInfoMapper->loadById($parentId);
                if ($parentCommentInfo) {
                    $parentCommentInfo->setComments($parentCommentInfo->getComments() + 1);
                    $this->commentInfoMapper->update($parentCommentInfo);
                } else {
                    $this->logger->warning('Parent comment info not found for update', ['parentId' => $parentId]);
                }
            }

            $commentResponse = $result->getArrayCopy();
            $commentResponse['user'] = $this->userMapper->loadUserInfoById($this->currentUserId);

            $this->logger->info('Comment created successfully', ['commentResponse' => $commentResponse]);
			$response = [$commentResponse];

            return [
                'status' => 'success',
				'counter' => count($response),
                'ResponseCode' => 11608,
                'affectedRows' => $response,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Error occurred while creating comment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->respondWithError(41602);
        } finally {
            $this->logger->debug('createComment function execution completed');
        }
    }

    public function fetchByParentId(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $parentId = $args['parent'] ?? null;

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        if ($parentId !== null && !self::isValidUUID($parentId)) {
            return $this->respondWithError(30209);
        }

        $this->logger->info("CommentService.fetchByParentId started");

        $results = $this->commentMapper->fetchByParentId($parentId, $this->currentUserId, $offset, $limit);

        return $results;
    }

    public function fetchAllByPostId(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $postId = $args['postid'] ?? null;

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        if ($postId !== null && !self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        $this->logger->info("CommentService.fetchAllByPostId started");

        $results = $this->commentMapper->fetchAllByPostId($postId, $this->currentUserId, $offset, $limit);
        return $results;
    }

    public function fetchAllByPostIdetaild(string $postId, int $offset = 0, int $limit = 10,?string $contentFilterBy = null): array
    {
        return $this->commentMapper->fetchAllByPostIdetaild($postId, $this->currentUserId, $offset, $limit,$contentFilterBy);
    }
}
