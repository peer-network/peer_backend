<?php

namespace Fawaz\App;

use Fawaz\App\Pool;
use Fawaz\Database\PoolMapper;
use Psr\Log\LoggerInterface;

class PoolService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected PoolMapper $poolMapper)
    {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    public static function isValidUUID(string $uuid): bool
    {
        return preg_match('/^\{?[a-fA-F0-9]{8}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{4}\-[a-fA-F0-9]{12}\}?$/', $uuid) === 1;
    }

    private function respondWithError(int $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->warning('Unauthorized access attempt');
            return false;
        }
        return true;
    }

    public function fetchPool(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $this->logger->info("WalletService.fetchPool started");

        $fetchPool = $this->poolMapper->fetchPool($args);
        return $fetchPool;
    }

    public function callGemster(): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        return $this->poolMapper->getTimeSorted();
    }

    public function callGemsters(string $day = 'D0'): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];

        if (!in_array($day, $dayActions, true)) {
            return $this->respondWithError(30223);
        }

        return $this->poolMapper->getTimeSortedMatch($day);
    }
    
    public function getActionPrices(): ?array
    {
        $this->logger->info('PoolService.getActionPrices: Calling fetchCurrentActionPrices');
        try {
            $prices = $this->poolMapper->fetchCurrentActionPrices();
            $this->logger->info('PoolService.getActionPrices: Retrieved prices', $prices ?: []);
            return $prices;
        } catch (\Throwable $e) {
            $this->logger->error('PoolService.getActionPrices exception', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}