<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Tag;
use Fawaz\Database\TagMapper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Database\Interfaces\TransactionManager;

class TagService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(protected PeerLoggerInterface $logger, protected TagMapper $tagMapper, protected TransactionManager $transactionManager)
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

    public function createTag(string $tagName): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning("TagService.createTag: Unauthorized action attempted.");
            return $this::respondWithError(60501);
        }

        $this->logger->debug('TagService.createTag started');
        $tagName = !empty($tagName) ? strtolower(trim($tagName)) : null;

        try {
            $this->transactionManager->beginTransaction();
            $tagValid = new Tag(['name' => $tagName], ['name']);
            $tag = $this->tagMapper->loadByName($tagName);

            if ($tag) {
                $this->transactionManager->rollback();
                $this->logger->error('TagService.createTag: Tag already exists', ['tagName' => $tagName]);
                return $this::respondWithError(21702);//'Tag already exists.'
            }

            $tagId = $this->generateUUID();

            $tagData = ['tagid' => $tagId, 'name' => $tagName];
            $tag = new Tag($tagData);

            if (!$this->tagMapper->insert($tag)) {
                $this->transactionManager->rollback();
                $this->logger->error("TagService.createTag: Failed to insert tag into database", ['tagName' => $tagName]);
                return $this->respondWithError(41703);
            }

            $this->transactionManager->commit();
            return $this->createSuccessResponse(11702, [$tagData]);

        } catch (ValidationException $e) {
            $this->transactionManager->rollback();
            $this->logger->info("TagService.createTag: Validation error occurred", ['error' => $e->getMessage()]);
            return $this->respondWithError(40301);
        } catch (\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error("TagService.createTag: Error occurred while creating tag", ['error' => $e->getMessage()]);
            return $this->respondWithError(40301);
        } finally {
            $this->logger->debug('TagService.createTag: createTag function execution completed');
        }
    }

    public function fetchAll(?array $args = []): array
    {
        $this->logger->debug("TagService.fetchAll started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        try {
            $tags = $this->tagMapper->fetchAll($offset, $limit);
            $result = array_map(fn (Tag $tag) => $tag->getArrayCopy(), $tags);

            return $this::createSuccessResponse(11701, $result);

        } catch (\Throwable $e) {
            $this->logger->error("TagService.fetchAll: Error fetching tags", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this::respondWithError(41702);
        }
    }

    public function loadTag(array $args): array
    {
        $this->logger->debug("TagService.loadTag started");

        try {

            if (isset($args['tagName']) && !empty($args['tagName'])) {
                $args['tagName'] = strtolower(trim((string) $args['tagName']));
                $tagData = ['name' => $args['tagName']];
                $tag = new Tag($tagData, ['name']);
            } else {
                $this->logger->debug("TagService.loadTag: tagName parameter is missing or empty.");
                return $this::respondWithError(30101);
            }

            $tags = $this->tagMapper->searchByName($args);

            if ($tags === false) {
                return $this::createSuccessResponse(21701, []);
            }

            $this->logger->info("TagService.loadTag successfully fetched tags", [
                'count' => count($tags),
            ]);

            $result = array_map(fn (Tag $tag) => $tag->getArrayCopy(), $tags);

            return $this::createSuccessResponse(11701, $result);

        } catch (\Throwable $e) {
            $this->logger->error("TagService.loadTag: Error occurred while loading tags", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this::respondWithError(40301);
        }
    }
}
