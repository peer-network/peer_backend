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

    private function respondWithError(string $message): array
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
            return $this->respondWithError('Unauthorized.');
        }

        $this->logger->info("WalletService.fetchPool started");

        $fetchPool = $this->poolMapper->fetchPool($args);
        return $fetchPool;
    }

    public function callFetchWinsLog(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];
        $day = $args['day'] ?? 'D0';

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this->respondWithError('Invalid day parameter provided.');
        }

        return $this->poolMapper->fetchWinsLog($this->currentUserId, $args, 'win');
    }

    public function callFetchPaysLog(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];
        $day = $args['day'] ?? 'D0';

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this->respondWithError('Invalid day parameter provided.');
        }

        return $this->poolMapper->fetchWinsLog($this->currentUserId, $args, 'pay');
    }

    public function callGemster(): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        return $this->poolMapper->getTimeSorted();
    }

    public function callGemsters(string $day = 'D0'): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized');
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this->respondWithError('Invalid day parameter provided.');
        }

        return $this->poolMapper->getTimeSortedMatch($day);
    }

    public function getPercentBeforeTransaction(string $userId, int $tokenAmount): array
    {
        //$this->logger->info('WalletService.getPercentBeforeTransaction started');
        return $this->poolMapper->getPercentBeforeTransaction($userId, $tokenAmount);
    }

    public function loadLiquidityById(string $userId): array
    {
        $this->logger->info('WalletService.loadLiquidityById started');

        try {
            $results = $this->poolMapper->loadLiquidityById($userId);

            if ($results !== false && $results !== 0.0) {
                $success = [
                    'status' => 'success',
                    'ResponseCode' => 'Liquidity data prepared successfully',
                    'affectedRows' => ['currentliquidity' => $results],
                ];
                return $success;
            }

            return $this->respondWithError('No liquidity found for the user.');
        } catch (\Exception $e) {
            return $this->respondWithError('Failed to retrieve liquidity list.');
        }
    }

    public function getUserWalletBalance(string $userId): float
    {
        $this->logger->info('WalletService.getUserWalletBalance started');

        try {
            $results = $this->poolMapper->getUserWalletBalance($userId);

            if ($results !== false) {
                return $results;
            }

            return 0.0;
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    public function deductFromWallet(string $userId, ?array $args = []): ?array
    {
        $this->logger->info('WalletService.deductFromWallet started');

        try {
            $response = $this->poolMapper->deductFromWallets($userId, $args);

            if ($response['status'] === 'success') {
                return $response;
            } else {
                return $response;
            }

        } catch (\Exception $e) {
            return $this->respondWithError('Unknown Error.');
        }
    }

    public function callUserMove(): ?array
    {
        $this->logger->info('WalletService.callUserMove started');

        try {
            $response = $this->poolMapper->callUserMove($this->currentUserId);
            return [
                'status' => 'success',
                'ResponseCode' => $response['ResponseCode'],
                'affectedRows' => $response['affectedRows'],
            ];

        } catch (\Exception $e) {
            return $this->respondWithError('Unknown Error.');
        }
    }
}
