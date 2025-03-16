<?php

namespace Fawaz\App;

use Fawaz\App\Comment;
use Fawaz\App\CommentInfo;
use Fawaz\Database\CommentMapper;
use Fawaz\Database\CommentInfoMapper;
use Fawaz\Database\PostInfoMapper;
use Psr\Log\LoggerInterface;

class CommentService
{
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger,
        protected CommentMapper $commentMapper,
        protected CommentInfoMapper $commentInfoMapper,
        protected PostInfoMapper $postInfoMapper
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
                return $this->respondWithError("$field is required");
            }
        }
        return [];
    }

    public function createComment(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (empty($args)) {
            return $this->respondWithError('No arguments provided. Please provide valid input parameters.');
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
            return $this->respondWithError('Invalid post ID', ['postid' => $postId]);
        }

        if ($parentId !== null && !$this->validateUUID($parentId)) {
            $this->logger->warning('Invalid parent ID', ['parentId' => $parentId]);
            $parentId = null;
        }

        if ($content === '') {
            return $this->respondWithError('Content is required');
        }

        try {
            $commentId = $this->generateUUID();
            if (empty($commentId)) {
                return $this->respondWithError('Failed to generate comment ID');
            }

            $commentData = [
                'commentid' => $commentId,
                'userid' => $this->currentUserId,
                'postid' => $postId,
                'parentid' => $parentId,
                'content' => $content,
            ];
            $comment = new Comment($commentData);
            $result = $this->commentMapper->insert($comment);

            if (!$result) {
                return $this->respondWithError('Failed to insert comment');
            }

            $postInfo = $this->postInfoMapper->loadById($postId);
            if (!$postInfo) {
                return $this->respondWithError('Post not found');
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

            // If the comment has a parent, increment the "comments" field for the parent in comment_info
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
            $commentResponse['user'] = $this->commentMapper->loadUserInfoById($this->currentUserId);

            $this->logger->info('Comment created successfully', ['commentResponse' => $commentResponse]);

            return [
                'status' => 'success',
                'ResponseCode' => 'Comment saved successfully',
                'affectedRows' => [$commentResponse],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Error occurred while creating comment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->respondWithError('Failed to create comment');
        } finally {
            $this->logger->debug('createComment function execution completed');
        }
    }

    public function fetchByParentId(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $parentId = $args['parent'] ?? null;

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        if ($parentId !== null && !self::isValidUUID($parentId)) {
            return $this->respondWithError('Invalid uuid input.');
        }

        $this->logger->info("PostService.findPostser started");

        $results = $this->commentMapper->fetchByParentId($parentId, $this->currentUserId, $offset, $limit);
        return $results;
    }
}
