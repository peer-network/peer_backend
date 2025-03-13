<?php

namespace Fawaz\App;

use Fawaz\App\Wallet;
use Fawaz\Database\WalletMapper;
use Psr\Log\LoggerInterface;

class PoolService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected WalletMapper $walletMapper)
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

    public function createTransaction(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized.');
        }

        if (empty($args)) {
            return $this->respondWithError('Could not find mandatory args.');
        }

        $this->logger->info('WalletService.createTransaction started');

        $token = $this->walletMapper->getPeerToken();
        $userid = $this->currentUserId;
        $postid = $args['postid'] ?? '';
        $fromid = $args['fromid'] ?? '';
        $numbers = $args['numbers'] ?? 0;
        $whereby = $args['whereby'] ?? 0;
        $createdat = $args['createdat'] ?? (new \DateTime())->format('Y-m-d H:i:s.u');

        // Validate input parameters
        try {
            // Create the Survey
            $walletData = [
                'token' => $token,
                'userid' => $userid,
                'postid' => $postid,
                'fromid' => $fromid,
                'numbers' => $numbers,
                'whereby' => $whereby,
                'createdat' => $createdat,
            ];
            $towallet = new Wallet($walletData);
            $this->walletMapper->insert($towallet);

            $this->logger->info('Transaction created successfully', ['token' => $token]);
            return [
                'status' => 'success',
                'affectedRows' => $walletData,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to create Transaction', ['args' => $args, 'exception' => $e]);
            return $this->respondWithError('Failed to create Transaction.');
        }
    }

    public function fetchPool(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized.');
        }

        $this->logger->info("WalletService.fetchPool started");

        $fetchPool = $this->walletMapper->fetchPool($args);
        return $fetchPool;
    }

    public function fetchAll(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized.');
        }

        $this->logger->info("WalletService.fetchAll started");

        $fetchAll = array_map(
            static function (Wallet $wallet) {
                $data = $wallet->getArrayCopy();
                return $data;
            },
            $this->walletMapper->fetchAll($args)
        );

        return $fetchAll;
    }

    public function fetchWalletById(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized.');
        }

        $userId = $this->currentUserId;
        $postId = $args['postid'] ?? null;
        $fromId = $args['fromid'] ?? null;

        if ($postId === null && $fromId === null && !self::isValidUUID($userId)) {
            return $this->respondWithError('At least one of postid, or fromid is required.');
        }

        if ($postId !== null && !self::isValidUUID($postId)) {
            return $this->respondWithError('Invalid postid provided.');
        }

        if ($fromId !== null && !self::isValidUUID($fromId)) {
            return $this->respondWithError('Invalid fromid provided.');
        }

        $this->logger->info("WalletService.fetchWalletById started");

        try {
            $wallets = $this->walletMapper->loadWalletById($args, $this->currentUserId);

            if ($wallets === false) {
                return $this->respondWithError('Failed to fetch wallets from database.');
            }

            $walletData = array_map(
                static function (Wallet $wallet) {
                    return $wallet->getArrayCopy();
                },
                $wallets
            );

            $this->logger->info("WalletService.fetchWalletById successfully fetched wallets", [
                'count' => count($walletData),
            ]);

            $success = [
                'status' => 'success',
                'counter' => count($walletData),
                'ResponseCode' => 'Successfully fetched wallets',
                'affectedRows' => $walletData
            ];

            return $success;

        } catch (Exception $e) {
            $this->logger->error("Error occurred in WalletService.fetchWalletById", [
                'error' => $e->getMessage(),
                'args' => $args,
            ]);
            return $this->respondWithError('An internal error occurred.');
        }
    }

    public function callFetchWinsLog(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized.');
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];
        $day = $args['day'] ?? 'D0';

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this->respondWithError('Invalid day parameter provided.');
        }

        return $this->walletMapper->fetchWinsLog($this->currentUserId, $args, 'win');
    }

    public function callFetchPaysLog(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized.');
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];
        $day = $args['day'] ?? 'D0';

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this->respondWithError('Invalid day parameter provided.');
        }

        return $this->walletMapper->fetchWinsLog($this->currentUserId, $args, 'pay');
    }

    public function callGlobalWins(): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized.');
        }

        return $this->walletMapper->callGlobalWins();
    }

    public function callGemster(): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized.');
        }

        return $this->walletMapper->getTimeSorted();
    }

    public function callGemsters(string $day = 'D0'): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError('Unauthorized.');
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this->respondWithError('Invalid day parameter provided.');
        }

        return $this->walletMapper->getTimeSortedMatch($day);
    }

    public function getPercentBeforeTransaction(string $userId, int $tokenAmount): array
    {
        //$this->logger->info('WalletService.getPercentBeforeTransaction started');
        return $this->walletMapper->getPercentBeforeTransaction($userId, $tokenAmount);
    }

    public function loadLiquidityById(string $userId): array
    {
        $this->logger->info('WalletService.loadLiquidityById started');

        try {
            $results = $this->walletMapper->loadLiquidityById($userId);

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
            //$results = $this->walletMapper->getUserWalletBalance($userId);
            $results = $this->walletMapper->getUserWalletBalances($userId);

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
            //$response = $this->walletMapper->deductFromWallet($userId, $args);
            $response = $this->walletMapper->deductFromWallets($userId, $args);

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
            $response = $this->walletMapper->callUserMove($this->currentUserId);
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
