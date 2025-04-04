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

    private function validateRequiredFields(array $args, array $requiredFields): array
    {
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                return $this->respondWithError("$field is required");
            }
        }
        return [];
    }

    public function insert(Contactus $contact): ?Contactus
    {
        return $this->contactUsMapper->insert($contact);
    }

    public function checkRateLimit(string $ip): bool
    {
        return $this->contactUsMapper->checkRateLimit($ip);
    }

    public function loadById(string $type, string $value): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (!in_array($type, ['id', 'name'], true)) {
            return $this->respondWithError('Invalid type provided.');
        }

        if (empty($value)) {
            return $this->respondWithError('At least one of id or name is required.');
        }

        if ($type === 'id' && !self::isValidUUID($value)) {
            return $this->respondWithError('Invalid ID provided.');
        }

        $this->logger->info("ContactusService.loadById started", [
            'type' => $type,
            'value' => $value,
        ]);

        try {
            $exist = ($type === 'id') ? $this->contactUsMapper->loadById($value) : $this->contactUsMapper->loadByName($value);

            if ($exist === null) {
                return $this->respondWithError('Failed to fetch contact from database.');
            }

            $existData = array_map(fn(Contactus $contact) => $contact->getArrayCopy(), $exist);

            $this->logger->info("ContactusService.loadById successfully fetched contact", [
                'type' => $type,
                'value' => $value,
                'count' => count($existData),
            ]);

            return $existData;

        } catch (\Throwable $e) {
            $this->logger->error("Error occurred in ContactusService.loadById", [
                'error' => $e->getMessage(),
                'type' => $type,
                'value' => $value,
            ]);

            return $this->respondWithError('An internal error occurred.');
        }
    }

    public function fetchAll(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        if (empty($args)) {
            return $this->respondWithError('args is required.');
        }

        $this->logger->info("ContactusService.fetchAll started", [
            'args' => $args,
        ]);

        try {
            $exist = $this->contactUsMapper->fetchAll($args);

            if ($exist === null) {
                return $this->respondWithError('Failed to fetch contact from database.');
            }

            $existData = array_map(fn(Contactus $contact) => $contact->getArrayCopy(), $exist);

            $this->logger->info("ContactusService.loadById successfully fetched contact", [
                'args' => $args,
                'count' => count($existData),
            ]);

            return $existData;

        } catch (\Throwable $e) {
            $this->logger->error("Error occurred in ContactusService.loadById", [
                'error' => $e->getMessage(),
                'args' => $args,
            ]);

            return $this->respondWithError('An internal error occurred.');
        }
    }
}
