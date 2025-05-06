<?php

namespace Fawaz\App;

use Fawaz\App\Tag;
use Fawaz\Database\TagMapper;
use Psr\Log\LoggerInterface;

class TagService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected TagMapper $tagMapper)
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
        if (empty($tagName)) {
            return false;
        }

        $tagName = htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8'); // Schutz vor XSS

        $length = strlen($tagName);
        return $length >= 2 && $length <= 50 && preg_match('/^[a-zA-Z]+$/', $tagName);
    }

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    private function createSuccessResponse(string $message, array $data = []): array
    {
        return ['status' => 'success', 'counter' => count($data), 'ResponseCode' => $message, 'affectedRows' => $data];
    }

    public function createTag(string $tagName): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info('TagService.createTag started');
        $tagName = !empty($tagName) ? trim($tagName) : null;

        try {
            $tagValid = new Tag(['name' => $tagName], ['name']);
            $tag = $this->tagMapper->loadByName($tagName);

            if ($tag) {
                return $this->respondWithError('Tag already exists.');
            }

            $tagId = $this->generateUUID();
            if (empty($tagId)) {
                $this->logger->critical('Failed to generate tag ID');
                return $this->respondWithError(41704);
            }

            $tagData = ['tagid' => $tagId, 'name' => $tagName];
            $tag = new Tag($tagData);

            if (!$this->tagMapper->insert($tag)) {
                return $this->respondWithError(41703);
            }

            return $this->createSuccessResponse(11702, [$tagData]);

        } catch (\Throwable $e) {
            return $this->respondWithError(40301);
        } catch (ValidationException $e) {
            return $this->respondWithError(40301);
        } finally {
            $this->logger->debug('createTag function execution completed');
        }
    }

    public function fetchAll(?array $args = []): array
    {
        $this->logger->info("TagService.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        try {
            $tags = $this->tagMapper->fetchAll($offset, $limit);
            $result = array_map(fn(Tag $tag) => $tag->getArrayCopy(), $tags);

            return $this->createSuccessResponse(11701, $result);

        } catch (\Throwable $e) {
            return $this->respondWithError(41702);
        }
    }

    public function loadTag(array $args): array
    {
        $this->logger->info("TagService.loadTag started");

        try {

            if (isset($args['tagName']) && !empty($args['tagName'])) {
                $tagData = ['name' => $args['tagName']];
                $tag = new Tag($tagData, ['name']);
            } else {
                return $this->respondWithError(30101);
            }

            $tags = $this->tagMapper->searchByName($args);

            if ($tags === false) {
                return $this->respondWithError(41702);
            }

            $this->logger->info("TagService.loadTag successfully fetched tags", [
                'count' => count($tags),
            ]);

            $result = array_map(fn(Tag $tag) => $tag->getArrayCopy(), $tags);

            return $this->createSuccessResponse(11701, $result);

        } catch (\Throwable $e) {
            $this->logger->error("Error occurred in TagService.loadTag", [
                'error' => $e->getMessage(),
            ]);
            return $this->respondWithError(40301);
        }
    }
}
