<?php

namespace Fawaz\App;

use Fawaz\Database\McapMapper;
use Psr\Log\LoggerInterface;

class McapService
{
    protected ?string $currentUserId = null;

    public function __construct(
        protected LoggerInterface $logger,
        protected McapMapper $mcapMapper
    ) {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    private function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    protected function createSuccessResponse(string $message, array|object $data = [], bool $countEnabled = true, ?string $countKey = null): array 
    {
        $response = [
            'status' => 'success',
            'ResponseCode' => $message,
            'affectedRows' => $data,
        ];

        if ($countEnabled && is_array($data)) {
            if ($countKey !== null && isset($data[$countKey]) && is_array($data[$countKey])) {
                $response['counter'] = count($data[$countKey]);
            } else {
                $response['counter'] = count($data);
            }
        }

        return $response;
    }
    
    public function loadLastId(): array
    {

        $this->logger->info('McapService.loadLastId started');

        try {
            $fetchUpdate = $this->mcapMapper->fetchAndUpdateMarketPrices();
            if (isset($fetchUpdate['status']) && $fetchUpdate['status'] === 'error') {
                return $fetchUpdate;
            }

            $results = $this->mcapMapper->loadLastId();

            if ($results !== false) {
                $affectedRows = $results->getArrayCopy();
                $this->logger->info("McapService.loadLastId mcap found", ['affectedRows' => $affectedRows]);
                $success = [
                    'status' => 'success',
                    'ResponseCode' => 11021,
                    'affectedRows' => $affectedRows,
                ];
                return $success;
            }

            return $this->respondWithError(31201);
        } catch (\Exception $e) {
            return $this->respondWithError(41206);
        }
    }

    public function fetchAll(?array $args = []): array
    {

        $this->logger->info('McapService.fetchAll started');

        try {
            $users = $this->mcapMapper->fetchAll($args, $this->currentUserId);
            $fetchAll = array_map(fn(User $user) => $user->getArrayCopy(), $users);

            if ($fetchAll) {
                $success = [
                    'status' => 'success',
                    'counter' => count($fetchAll),
                    'ResponseCode' => 11009,
                    'affectedRows' => $fetchAll,
                ];
                return $success;
            }

            return $this->createSuccessResponse(21001);
        } catch (\Exception $e) {
            return $this->respondWithError(41207);
        }
    }
}
