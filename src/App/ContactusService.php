<?php
declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Contactus;
use Fawaz\Database\ContactusMapper;
use Psr\Log\LoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Utils\ResponseHelper;

class ContactusService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected ContactusMapper $contactUsMapper, protected TransactionManager $transactionManager)
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
        $contactConfig = ConstantsConfig::contact();
        return $Name &&
            strlen($Name) >= $contactConfig['NAME']['MIN_LENGTH'] && 
            strlen($Name) <= $contactConfig['NAME']['MAX_LENGTH'] && 
            preg_match('/' . $contactConfig['NAME']['PATTERN'] . '/u', $Name);
    }

    private function validateRequiredFields(array $args, array $requiredFields): array
    {
        foreach ($requiredFields as $field) {
            if (empty($args[$field])) {
                return $this::respondWithError(00000);//"$field is required"
            }
        }
        return [];
    }

    public function insert(Contactus $contact): ?Contactus
    {

        try {
            $this->transactionManager->beginTransaction();
            $response = $this->contactUsMapper->insert($contact);

            $this->transactionManager->commit();

            return $response;
        }catch (\Throwable $e) {
            $this->transactionManager->rollBack();
            $this->logger->error("Error occurred in ContactusService.insert", [
                'error' => $e->getMessage(),
                'contact' => $contact->getArrayCopy(),
            ]);
            return null;
        }
    }

    public function checkRateLimit(string $ip): bool
    {
        try{
            $this->transactionManager->beginTransaction();

            $response = $this->contactUsMapper->checkRateLimit($ip);

            if(!$response) {
                $this->logger->info("Rate limit check failed for IP: $ip");
                $this->transactionManager->rollBack();
                return false;
            }
            $this->transactionManager->commit();

            return $response;
        }catch (\Throwable $e) {
            $this->transactionManager->rollBack();

            $this->logger->error("Error occurred in ContactusService.checkRateLimit", [
                'error' => $e->getMessage(),
                'ip' => $ip,
            ]);
            return false;
        }

    }

    public function loadById(string $type, string $value): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (!in_array($type, ['id', 'name'], true)) {
            return $this::respondWithError(30105);
        }

        if (empty($value)) {
            return $this::respondWithError(30102);
        }

        if ($type === 'id') {
            if (!ctype_digit($value)) {
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

            return $this::respondWithError(40301);
        }
    }

    public function fetchAll(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        if (empty($args)) {
            return $this::respondWithError(30101);
        }

        $this->logger->debug("ContactusService.fetchAll started", [
            'args' => $args,
        ]);

        try {
            $exist = $this->contactUsMapper->fetchAll($args);

            if (empty($exist)) {
                return $this::respondWithError(40401);
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

            return $this::respondWithError(40301);
        }
    }
}
