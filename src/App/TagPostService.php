<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\TagPost;
use Fawaz\Database\TagPostMapper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Database\TagMapper;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Database\Interfaces\TransactionManager;

class TagPostService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(protected PeerLoggerInterface $logger, protected TagPostMapper $tagPostMapper, protected TagMapper $tagMapper, protected TransactionManager $transactionManager)
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
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0x0fff) | 0x4000,
            \mt_rand(0, 0x3fff) | 0x8000,
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff),
            \mt_rand(0, 0xffff)
        );
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
        $tagNameConfig = ConstantsConfig::post()['TAG'];
        $inputConfig  = ConstantsConfig::input();
        $controlPattern = '/'.$inputConfig['FORBID_CONTROL_CHARS_PATTERN'].'/u';
        return $tagName &&
            strlen($tagName) >= $tagNameConfig['MIN_LENGTH'] &&
            strlen($tagName) <= $tagNameConfig['MAX_LENGTH'] &&
            preg_match($controlPattern, $tagName) === 0;
    }

    private function validateTagName(string $tagName): array|bool
    {
        if ($tagName === '') {
            return $this::respondWithError(30101);
        }

        $tagNameConfig = ConstantsConfig::post()['TAG'];
        $inputConfig  = ConstantsConfig::input();
        $controlPattern = '/'.$inputConfig['FORBID_CONTROL_CHARS_PATTERN'].'/u';

        if (strlen($tagName) < $tagNameConfig['MIN_LENGTH'] ||
            strlen($tagName) > $tagNameConfig['MAX_LENGTH'] ||
            preg_match($controlPattern, $tagName) === 1) {
            return $this::respondWithError(30255);
        }

        return true;
    }

    public function handleTags(array $tags, string $postId, int $maxTags = 10): ?array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $maxTags = min(max($maxTags, 1), 10);
        if (count($tags) > $maxTags) {
            return $this::respondWithError(30211);
        }

        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            // Validate tagName
            if (!$this->isValidTagName($tagName)) {
                return $this::respondWithError(30255);
            }

            $tag = $this->tagMapper->loadByName($tagName);
            if (!$tag) {
                $tag = $this->createTag($tagName);
            }
            $tagPost = new TagPost([
                'postid' => $postId,
                'tagid' => $tag->getTagId(),
                'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
            ]);
            $this->tagPostMapper->insert($tagPost);
        }

        return ['status' => 'success'];
    }

    public function createTag(string $tagName): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('TagService.createTag started');

        $tagName = trim($tagName);
        if (!$this->isValidTagName($tagName)) {
            return $this::respondWithError(30255);
        }

        try {
            $tag = $this->tagMapper->loadByName($tagName);

            if ($tag) {
                return $this::createSuccessResponse(21702);
            }
            $this->transactionManager->beginTransaction();

            $tagId = $this->generateUUID();

            $tagData = ['tagid' => $tagId, 'name' => $tagName];
            $tag = new Tag($tagData);

            if (!$this->tagMapper->insert($tag)) {
                $this->transactionManager->rollback();
                $this->logger->error('Failed to insert tag into database', ['tagName' => $tagName]);
                return $this::respondWithError(41703);
            }

            $this->transactionManager->commit();
            $this->logger->info('Tag created successfully', ['tagName' => $tagName]);
            return $this::createSuccessResponse(11702, [$tagData]);

        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Error occurred while creating tag', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this::respondWithError(41701);
        } finally {
            $this->logger->debug('createTag function execution completed');
        }
    }

    public function fetchAll(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $this->logger->debug('TagPostService.fetchAll started');

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        try {
            $TagPost = $this->tagPostMapper->fetchAll($offset, $limit);
            $result = array_map(fn (TagPost $tag) => $tag->getArrayCopy(), $TagPost);

            $this->logger->info('TagPost fetched successfully', ['count' => count($result)]);
            return $this::createSuccessResponse(11701, [$result]);

        } catch (\Throwable $e) {
            $this->logger->error('Error fetching TagPost', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this::respondWithError(41702);
        }
    }
}
