<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Contactus;
use Fawaz\Database\ContactusMapper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Utils\ResponseHelper;

class ContactusService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(protected PeerLoggerInterface $logger, protected ContactusMapper $contactUsMapper, protected TransactionManager $transactionManager)
    {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('ContactusService.checkAuthentication: Unauthorized action attempted');
            return false;
        }
        return true;
    }

    public function insert(Contactus $contact): ?Contactus
    {

        try {
            $this->transactionManager->beginTransaction();
            $response = $this->contactUsMapper->insert($contact);

            $this->transactionManager->commit();

            return $response;
        } catch (\Throwable $e) {
            $this->transactionManager->rollBack();
            $this->logger->error("Error occurred in ContactusService.insert", [
                'error' => $e->getMessage(),
                'msgid' => $contact->getMsgId(),
            ]);
            return null;
        }
    }

    public function checkRateLimit(string $ip): bool
    {
        try {
            $this->transactionManager->beginTransaction();

            $response = $this->contactUsMapper->checkRateLimit($ip);

            if (!$response) {
                $this->logger->info("Rate limit check failed for IP");
                $this->transactionManager->rollBack();
                return false;
            }
            $this->transactionManager->commit();

            return $response;
        } catch (\Throwable $e) {
            $this->transactionManager->rollBack();

            $this->logger->error("Error occurred in ContactusService.checkRateLimit", [
                'error' => $e->getMessage(),
            ]);
            return false;
        }

    }

    public function loadById(string $type, string $value): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning('ContactusService.loadById: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (!in_array($type, ['id', 'name'], true)) {
            $this->logger->debug('ContactusService.loadById: Invalid type provided', ['type' => $type]);
            return $this::respondWithError(30105);
        }

        if (empty($value)) {
            $this->logger->debug('ContactusService.loadById: Empty value provided', ['type' => $type]);
            return $this::respondWithError(30102);
        }

        if ($type === 'id') {
            if (!ctype_digit($value)) {
                $this->logger->debug('ContactusService.loadById: Invalid id value provided', ['value' => $value]);
                return $this::respondWithError(30105);
            }
            $value = (int)$value;
        }

        $this->logger->debug("ContactusService.loadById started", [
            'type' => $type,
            'value' => $value,
        ]);

        try {
            $exist = ($type === 'id') ? $this->contactUsMapper->loadById($value) : $this->contactUsMapper->loadByName($value);

            if ($exist === null) {
                $this->logger->error('ContactusService.loadById: Contact not found', ['type' => $type, 'value' => $value]);
                return $this::respondWithError(40401);
            }

            $existData = $exist->getArrayCopy();

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

            $this->logger->error('ContactusService.loadById: Returning error response', ['responseCode' => 40301]);
            return $this::respondWithError(40301);
        }
    }

    public function fetchAll(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            $this->logger->warning('ContactusService.fetchAll: Authentication failed');
            return $this::respondWithError(60501);
        }

        if (empty($args)) {
            $this->logger->debug('ContactusService.fetchAll: Empty arguments provided');
            return $this::respondWithError(30101);
        }

        $this->logger->debug("ContactusService.fetchAll started", [
            'args' => $args,
        ]);

        try {
            $exist = $this->contactUsMapper->fetchAll($args);

            if (empty($exist)) {
                $this->logger->error('ContactusService.fetchAll: No contacts found', ['args' => $args]);
                return $this::respondWithError(40401);
            }

            $existData = array_map(fn (Contactus $contact) => $contact->getArrayCopy(), $exist);

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

            return $this::respondWithError(40301);
        }
    }
}
