<?php

namespace Fawaz\App;

use Fawaz\App\Contactus;
use Fawaz\Database\ContactusMapper;
use Psr\Log\LoggerInterface;

class ContactusService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected ContactusMapper $contactUsMapper)
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

	private function isValidName(?string $Name): bool
	{
		return $Name && strlen($Name) >= 2 && strlen($Name) <= 50 && preg_match('/^[a-zA-Z]+$/', $Name);
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
		$this->logger->info("ContactusService.fetchAll started");

		$offset = max((int)($args['offset'] ?? 0), 0);
		$limit = min(max((int)($args['limit'] ?? 10), 1), 20);

		try {
			$tags = $this->contactUsMapper->fetchAll($offset, $limit);
			$result = array_map(fn(Contactus $contact) => $contact->getArrayCopy(), $tags);

			return $this->createSuccessResponse('Contactus fetched successfully', $result);

		} catch (\Throwable $e) {
			return $this->respondWithError('Failed to fetch Contactus');
		}
	}

	public function loadById(string $tagId): array
	{
		return $this->loadTag('id', $tagId);
	}

	public function loadByName(string $name): array
	{
		return $this->loadTag('name', $name);
	}

	private function loadTag(string $type, string $value): array
	{
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

		if ($value === '') {
			return $this->respondWithError('At least one of ' . $type . ' is required.');
		}

		if ($type === 'id' && !self::isValidUUID($value)) {
			return $this->respondWithError('Invalid tagId provided.');
		}

		$this->logger->info("ContactusService.loadTag started");

		try {
			$tags = ($type === 'id') ? $this->contactUsMapper->loadById($value) : $this->contactUsMapper->loadByName($value);

			if ($tags === false) {
				return $this->respondWithError('Failed to fetch tags from database.');
			}

			$tagData = array_map(fn(Contactus $contact) => $contact->getArrayCopy(), $tags);

			$this->logger->info("ContactusService.loadTag successfully fetched tags", [
				'type' => $type,
				'value' => $value,
				'count' => count($tagData),
			]);

			return $tagData;

		} catch (\Throwable $e) {
			$this->logger->error("Error occurred in ContactusService.loadTag", [
				'error' => $e->getMessage(),
				'type' => $type,
				'value' => $value,
			]);
			return $this->respondWithError('An internal error occurred.');
		}
	}
}
