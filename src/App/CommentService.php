<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Comment;
use Fawaz\App\CommentInfo;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\CommentInfoMapper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\PostInfoMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;

class CommentService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected CommentMapper $commentMapper,
        protected CommentInfoMapper $commentInfoMapper,
        protected PostInfoMapper $postInfoMapper,
        protected UserMapper $userMapper,
        protected TransactionManager $transactionManager
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
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }

    protected function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->error('CommentService.checkAuthentication: Unauthorized access attempt');
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
                $this->logger->error('CommentService.validateRequiredFields: Missing required field', ['field' => $field]);
                return $this::respondWithError(30265);
            }
        }
        return [];
    }

    public function createComment(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('CommentService.createComment: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (empty($args)) {
            $this->logger->error('CommentService.createComment: Empty arguments provided');
            return $this::respondWithError(30101);
        }

        $this->logger->debug('CommentService.createComment started');

        $requiredFields = ['content', 'postid'];
        $validationErrors = $this->validateRequiredFields($args, $requiredFields);
        if (!empty($validationErrors)) {
            return $validationErrors;
        }

        $content = trim($args['content']);
        $postId = trim($args['postid']);
        $parentId = isset($args['parentid']) ? trim($args['parentid']) : null;

        if (!$this->validateUUID($postId)) {
            $this->logger->error('CommentService.createComment: Invalid postId', ['postId' => $postId]);
            return $this::respondWithError(30209, ['postid' => $postId]);
        }

        if ($parentId !== null && !$this->validateUUID($parentId)) {
            $this->logger->error('CommentService.createComment: Invalid parentId', ['parentId' => $parentId]);
            return $this::respondWithError(31603, ['parentId' => $parentId]);
        }

        if ($content === '') {
            $this->logger->error('CommentService.createComment: Empty content');
            return $this::respondWithError(30101);
        }

        if ($parentId !== null) {
            if (!$this->commentMapper->isParentTopLevel($parentId)) {
                $this->logger->error('CommentService.createComment: Parent is not top-level', ['parentId' => $parentId]);
                return $this::respondWithError(41604);
            }
        }

        try {
            $commentId = $this->generateUUID();

            $commentData = [
                'commentid' => $commentId,
                'userid' => $this->currentUserId,
                'postid' => $postId,
                'parentid' => $parentId,
                'content' => $content,
                'visibility_status' => 'normal',
            ];

            // Post speichern
            try {
                $comment = new Comment($commentData);
            } catch (\Throwable $e) {
                $this->logger->error('CommentService.createComment: Error occurred while creating comment', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                return $this::respondWithError(30265);
            }

            $result = $this->commentMapper->insert($comment);

            if (!$result) {
                $this->logger->error('CommentService.createComment: Failed to insert comment into database', ['commentData' => $commentData]);
                return $this::respondWithError(41602);
            }

            $postInfo = $this->postInfoMapper->loadById($postId);
            if (!$postInfo) {
                $this->logger->error('CommentService.createComment: PostInfo not found for postId', ['postId' => $postId]);
                return $this::respondWithError(31602);
            }

            $postInfo->setComments($postInfo->getComments() + 1);
            $this->postInfoMapper->update($postInfo);

            $commentInfoData = [
                'commentid' => $commentId,
                'userid' => $this->currentUserId,
                'likes' => 0,
                'reports' => 0,
                'comments' => 0
            ];
            $commentInfo = new CommentInfo($commentInfoData);
            $this->commentInfoMapper->insert($commentInfo);

            if (!empty($parentId)) {
                $parentCommentInfo = $this->commentInfoMapper->loadById($parentId);
                if ($parentCommentInfo) {
                    $parentCommentInfo->setComments($parentCommentInfo->getComments() + 1);
                    $this->commentInfoMapper->update($parentCommentInfo);
                } else {
                    $this->logger->error('CommentService.createComment: Parent comment info not found for update', ['parentId' => $parentId]);
                }
            }

            $commentResponse = $result->getArrayCopy();
            $commentResponse['user'] = $this->userMapper->loadUserInfoById($this->currentUserId);

            $this->logger->info('Comment created successfully', ['commentResponse' => $commentResponse]);
            $response = [$commentResponse];

            $this->logger->info('CommentService.createComment completed successfully');
            return [
                'status' => 'success',
                'counter' => count($response),
                'ResponseCode' => "11608",
                'affectedRows' => $response,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('CommentService.createComment: Error occurred while creating comment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this::respondWithError(41602);
        } finally {
            $this->logger->debug('CommentService.createComment function execution completed');
        }
    }

    public function fetchByParentId(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('CommentService.fetchByParentId: Authentication failed');
            return $this::respondWithError(60501);
        }

        $parentId = $args['parent'] ?? null;

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        if ($parentId !== null && !self::isValidUUID($parentId)) {
            $this->logger->error('CommentService.fetchByParentId: Invalid parentId', ['parentId' => $parentId]);
            return $this::respondWithError(30209);
        }

        $this->logger->debug("CommentService.fetchByParentId started");

        $results = $this->commentMapper->fetchByParentId($parentId, $this->currentUserId, $offset, $limit);

        return $results;
    }

    public function fetchAllByPostId(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->error('CommentService.fetchAllByPostId: Authentication failed');
            return $this::respondWithError(60501);
        }

        $postId = $args['postid'] ?? null;

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        if ($postId !== null && !self::isValidUUID($postId)) {
            $this->logger->error('CommentService.fetchAllByPostId: Invalid postId', ['postId' => $postId]);
            return $this::respondWithError(30209);
        }

        $this->logger->debug("CommentService.fetchAllByPostId started");

        $results = $this->commentMapper->fetchAllByPostId($postId, $this->currentUserId, $offset, $limit);
        return $results;
    }

    public function fetchAllByPostIdetaild(string $postId, int $offset, int $limit, array $specifications): array
    {
        return $this->commentMapper->fetchAllByPostIdetaild($postId, $specifications, $this->currentUserId, $offset, $limit);
    }
}
