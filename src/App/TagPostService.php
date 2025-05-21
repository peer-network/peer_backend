<?php

namespace Fawaz\App;

use Fawaz\App\TagPost;
use Fawaz\Database\TagPostMapper;
use Psr\Log\LoggerInterface;

class TagPostService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected TagPostMapper $tagPostMapper)
    {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function generateUUID(): string
    {
        return \sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0x0fff) | 0x4000,
            \mt_rand(0, 0x3fff) | 0x8000,
            \mt_rand(0, 0xffff), \mt_rand(0, 0xffff), \mt_rand(0, 0xffff)
        );
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning("Unauthorized action attempted.");
            return false;
        }
        return true;
    }

    private function isValidTagName(?string $tagName): bool
    {
        return $tagName && strlen($tagName) >= 2 && strlen($tagName) <= 50 && preg_match('/^[a-zA-Z]+$/', $tagName);
    }

    private function validateTagName(string $tagName): array|bool
    {
        if ($tagName === '') {
            return $this->respondWithError(30101);
        }

        if (strlen($tagName) < 2 || strlen($tagName) > 50 || !preg_match('/^[a-zA-Z]+$/', $tagName)) {
            return $this->respondWithError(30255);
        }

        return true;
    }

    public function handleTags(array $tags, string $postId, int $maxTags = 10): void
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $maxTags = min(max((int)($maxTags ?? 5), 1), 10);
        if (count($tags) > $maxTags) {
            return $this->respondWithError(30211);
        }

        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            // Validate tagName
            if (!$this->isValidTagName($tagName)) {
                return $this->respondWithError(30255);
            }

            $tag = $this->tagMapper->loadByName($tagName) ?? $this->createTag($tagName);
            $tagPost = new TagPost([
                'postid' => $postId,
                'tagid' => $tag->getTagId(),
                'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
            ]);
            $this->tagPostMapper->insert($tagPost);
        }
    }

    public function createTag(string $tagName): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('TagService.createTag started');

        $tagName = trim($tagName);
        if (!$this->isValidTagName($tagName)) {
            return $this->respondWithError(30255);
        }

        try {
            $tag = $this->tagMapper->loadByName($tagName);

            if ($tag) {
                return $this->createSuccessResponse(21702);
            }

            $tagId = $this->generateUUID();
            if (empty($tagId)) {
                $this->logger->critical('Failed to generate tag ID');
                return $this->respondWithError(41701);
            }

            $tagData = ['tagid' => $tagId, 'name' => $tagName];
            $tag = new Tag($tagData);

            if (!$this->tagMapper->insert($tag)) {
                $this->logger->error('Failed to insert tag into database', ['tagName' => $tagName]);
                return $this->respondWithError(41703);
            }

            $this->logger->info('Tag created successfully', ['tagName' => $tagName]);
            return $this->createSuccessResponse(11702, [$tagData]);

        } catch (\Throwable $e) {
            $this->logger->error('Error occurred while creating tag', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->respondWithError(41701);
        } finally {
            $this->logger->debug('createTag function execution completed');
        }
    }

    private function isValidTagName(?string $tagName): bool
    {
        return $tagName && strlen($tagName) >= 2 && strlen($tagName) <= 50 && preg_match('/^[a-zA-Z]+$/', $tagName);
    }

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    private function createSuccessResponse(string $message, array $data = []): array
    {
        return ['status' => 'success', 'counter' => count($data), 'ResponseCode' => $message, 'affectedRows' => $data];
    }

    public function fetchAll(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('TagPostService.fetchAll started');

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        try {
            $TagPost = $this->tagPostMapper->fetchAll($offset, $limit);
            $result = array_map(fn(TagPost $tag) => $tag->getArrayCopy(), $TagPost);

            $this->logger->info('TagPost fetched successfully', ['count' => count($result)]);
            return $this->createSuccessResponse(11701, [$result]);

        } catch (\Throwable $e) {
            $this->logger->error('Error fetching TagPost', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->respondWithError(41702);
        }
    }
}
