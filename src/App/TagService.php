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

	public function createTag(string $tagName): array
	{
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

		$this->logger->info('TagService.createTag started');

		$tagName = trim($tagName);
		if (!$this->isValidTagName($tagName)) {
			return $this->respondWithError('Invalid tagName. Must be 2-50 letters long and contain only letters.');
		}

		try {
			$tag = $this->tagMapper->loadByName($tagName);

			if ($tag) {
				return $this->respondWithError('Tag already exists.');
			}

			$tagId = $this->generateUUID();
			if (empty($tagId)) {
				$this->logger->critical('Failed to generate tag ID');
				return $this->respondWithError('Failed to generate tag ID');
			}

			$tagData = ['tagid' => $tagId, 'name' => $tagName];
			$tag = new Tag($tagData);

			if (!$this->tagMapper->insert($tag)) {
				return $this->respondWithError('Failed to insert tag into database');
			}

			return $this->createSuccessResponse('Tag created successfully', [$tagData]);

		} catch (\Throwable $e) {
			return $this->respondWithError('Failed to create tag');
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

			return $this->createSuccessResponse('Tags fetched successfully', $result);

		} catch (\Throwable $e) {
			return $this->respondWithError('Failed to fetch tags');
		}
	}

	public function loadTag(array $args): array
	{
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

		if (empty($args)) {
			return $this->respondWithError('At least one of args is required.');
		}

		$this->logger->info("TagService.loadTag started");

		try {
			$tags = $this->tagMapper->searchByName($args);

			if ($tags === false) {
				return $this->respondWithError('Failed to fetch tags from database.');
			}

			$this->logger->info("TagService.loadTag successfully fetched tags", [
				'count' => count($tags),
			]);

			$tagData = array_map(fn(Tag $tag) => $tag->getName(), $tags);

			return $this->createSuccessResponse('Tags fetched successfully', $tagData);

		} catch (\Throwable $e) {
			$this->logger->error("Error occurred in TagService.loadTag", [
				'error' => $e->getMessage(),
			]);
			return $this->respondWithError('An internal error occurred.');
		}
	}
}
