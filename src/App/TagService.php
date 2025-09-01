<?php
declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Tag;
use Fawaz\Database\TagMapper;
use Psr\Log\LoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Utils\ResponseHelper;

class TagService
{
    use ResponseHelper;
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

        $tagNameConfig = ConstantsConfig::post()['TAG'];
        $tagName = htmlspecialchars($tagName, ENT_QUOTES, 'UTF-8'); // Schutz vor XSS

        $length = strlen($tagName);
        return $length >= $tagNameConfig['MIN_LENGTH']
            && $length <= $tagNameConfig['MAX_LENGTH']
            && preg_match('/' . $tagNameConfig['PATTERN'] . '/u', $tagName);
    }

    public function createTag(string $tagName): array
    {
        if (!$this->checkAuthentication()) {
            return $this::createResponse(60501);
        }

        $this->logger->info('TagService.createTag started');
        $tagName = !empty($tagName) ? trim($tagName) : null;

        try {
            $tagValid = new Tag(['name' => $tagName], ['name']);
            $tag = $this->tagMapper->loadByName($tagName);

            if ($tag) {
                return $this::createResponse(21702);//'Tag already exists.'
            }

            $tagId = $this->generateUUID();
            if (empty($tagId)) {
                $this->logger->critical('Failed to generate tag ID');
                return $this::createResponse(41704);
            }

            $tagData = ['tagid' => $tagId, 'name' => $tagName];
            $tag = new Tag($tagData);

            if (!$this->tagMapper->insert($tag)) {
                return $this::createResponse(41703);
            }

            return $this::createResponse(11702, [$tagData]);

        } catch (ValidationException $e) {
            return $this::createResponse(40301);
        } catch (\Throwable $e) {
            return $this::createResponse(40301);
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

            return $this::createResponse(11701, $result);

        } catch (\Throwable $e) {
            return $this::createResponse(41702);
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
                return $this::createResponse(30101);
            }

            $tags = $this->tagMapper->searchByName($args);

            if ($tags === false) {
                return $this::createResponse(21701, []);
            }

            $this->logger->info("TagService.loadTag successfully fetched tags", [
                'count' => count($tags),
            ]);

            $result = array_map(fn(Tag $tag) => $tag->getArrayCopy(), $tags);

            return $this::createResponse(11701, $result);

        } catch (\Throwable $e) {
            $this->logger->error("Error occurred in TagService.loadTag", [
                'error' => $e->getMessage(),
            ]);
            return $this::createResponse(40301);
        }
    }
}
