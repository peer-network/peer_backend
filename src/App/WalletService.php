<?php

namespace Fawaz\App;

use Fawaz\App\Wallet;
use Fawaz\Database\WalletMapper;
use Psr\Log\LoggerInterface;

class WalletService
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

        $fetchPool = $this->walletMapper->fetchPool($args);
        return $fetchPool;
    }

    public function fetchAll(?array $args = []): array|false
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
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
            return $this->respondWithError(60501);
        }

        $userId = $this->currentUserId;
        $postId = $args['postid'] ?? null;
        $fromId = $args['fromid'] ?? null;

        if ($postId === null && $fromId === null && !self::isValidUUID($userId)) {
            return $this->respondWithError(30102);
        }

        if ($postId !== null && !self::isValidUUID($postId)) {
            return $this->respondWithError(30209);
        }

        if ($fromId !== null && !self::isValidUUID($fromId)) {
            return $this->respondWithError(30105);
        }

        $this->logger->info("WalletService.fetchWalletById started");

        try {
            $wallets = $this->walletMapper->loadWalletById($this->currentUserId, $args);

            if ($wallets === false) {
                return $this->respondWithError(41216);
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
                'ResponseCode' => 11209,
                'affectedRows' => $walletData
            ];

            return $success;

        } catch (Exception $e) {
            $this->logger->error("Error occurred in WalletService.fetchWalletById", [
                'error' => $e->getMessage(),
                'args' => $args,
            ]);
            return $this->respondWithError(40301);
        }
    }

    public function callFetchWinsLog(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];
        $day = $args['day'] ?? 'D0';

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this->respondWithError(30105);
        }

        return $this->walletMapper->fetchWinsLog($this->currentUserId, 'win', $args);
    }

    public function callFetchPaysLog(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];
        $day = $args['day'] ?? 'D0';

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this->respondWithError(30105);
        }

        return $this->walletMapper->fetchWinsLog($this->currentUserId, 'pay', $args);
    }

    public function callGlobalWins(): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        return $this->walletMapper->callGlobalWins();
    }

    public function callGemster(): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        return $this->walletMapper->getTimeSorted();
    }

    public function callGemsters(string $day = 'D0'): array
    {
        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this->respondWithError(30105);
        }

        $gemsters = $this->walletMapper->getTimeSortedMatch($day);

        if (isset($gemsters['affectedRows']['data'])) {
            $winstatus = $gemsters['affectedRows']['data'][0];
            unset($gemsters['affectedRows']['data'][0]);

            $userStatus= array_values($gemsters['affectedRows']['data']);
                    
            $affectedRows = [
                'winStatus' => $winstatus ?? [],
                'userStatus' => $userStatus ?? [],
            ];  
            
            return [
                'status' => $gemsters['status'],
                'counter' => $gemsters['counter'] ?? 0,
                'ResponseCode' => $gemsters['ResponseCode'],        
                'affectedRows' => $affectedRows
            ];
        } 
        return [
            'status' => $gemsters['status'],
            'counter' => 0,
            'ResponseCode' => $gemsters['ResponseCode'],
            'affectedRows' => []
        ];     
    }

    public function getPercentBeforeTransaction(string $userId, int $tokenAmount): array
    {
        $this->logger->info('WalletService.getPercentBeforeTransaction started');

        return $this->walletMapper->getPercentBeforeTransaction($userId, $tokenAmount);
    }

    public function loadLiquidityById(string $userId): array
    {
        $this->logger->info('WalletService.loadLiquidityById started');

        try {
            $results = $this->walletMapper->loadLiquidityById($userId);

            if ($results !== false ) {
                $success = [
                    'status' => 'success',
                    'ResponseCode' => 11204,
                    'currentliquidity' => $results,
                ];
                return $success;
            }

            return $this->createSuccessResponse(21203);
        } catch (\Exception $e) {
            return $this->respondWithError(41204);
        }
    }

    public function getUserWalletBalance(string $userId): float
    {
        $this->logger->info('WalletService.getUserWalletBalance started');

        try {
            $results = $this->walletMapper->getUserWalletBalance($userId);

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
            $response = $this->walletMapper->deductFromWallets($userId, $args);
            if ($response['status'] === 'success') {
                return $response;
            } else {
                return $response;
            }

        } catch (\Exception $e) {
            return $this->respondWithError(40301);
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
            return $this->respondWithError(41205);
        }
    }

    public function transferToken(array $args): array
    {
        $this->logger->info('WalletService.transferToken started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }
        try {
            $response = $this->walletMapper->transferToken($this->currentUserId, $args);
            if ($response['status'] === 'error') {
                return $response;
            } else {
                return [
                    'status' => 'success',
                    'ResponseCode' => 11211,
                    'affectedRows' => [
                        'tokenSend' => $response['tokenSend'],
                        'tokensSubstractedFromWallet' => $response['tokensSubstractedFromWallet'],
                        'createdat' => $response['createdat'] ?? ''
                    ],
                ];
            }

        } catch (\Exception $e) {
            return $this->respondWithError(0000); // Failed to transfer token
        }
    }

    /**
     * Get transcation history with Filter
     * 
     * 
     */
    
    public function transactionsHistory(array $args): array
    {
        $this->logger->info('WalletService.transactionsHistory started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }
        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        try {
            $results = $this->walletMapper->getTransactions($this->currentUserId, $args);

            return [
                'status' => 'success',
                'ResponseCode' => $results['ResponseCode'],
                'affectedRows' => $results['affectedRows']
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error in WalletService.transactionsHistory", ['exception' => $e->getMessage()]);
            return $this->respondWithError(0000);  // Error occurred while retrieving transaction history
        }

    }

    
    /**
     * Get Swap transcation history
     * 
     */
    public function getLiquidityPoolHistory(array $args): array
    {
        $this->logger->info('WalletService.getLiquidityPoolHistory started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        $validationResult = $this->validateOffsetAndLimit($args);
        if (isset($validationResult['status']) && $validationResult['status'] === 'error') {
            return $validationResult;
        }

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);

        try {
            $results = $this->walletMapper->getLiquidityPoolHistory($this->currentUserId, $offset, $limit);

            return [
                'status' => 'success',
                'ResponseCode' => $results['ResponseCode'],
                'affectedRows' => $results['affectedRows']
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error in WalletService.getLiquidityPoolHistory", ['exception' => $e->getMessage()]);
            return $this->respondWithError(0000);  // Error occurred while retrieving Liquidity Pool transaction history
        }
    }
    

    /**
     * Get Peer token price
     * 
     */
    public function getTokenPrice(): array
    {
        $this->logger->info('WalletService.getTokenPrice started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        try {
            $results = $this->walletMapper->getTokenPrice();
            return [
                'status' => 'success',
                'ResponseCode' => $results['ResponseCode'],
                'currentTokenPrice' => $results['currentTokenPrice'],
                'updatedAt' => $results['updatedAt'],
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error in WalletService.getTokenPrice", ['exception' => $e->getMessage()]);
            return $this->respondWithError(0000);  // Error occurred while retrieving token price
        }
    }

    
    /**
     * Update Swap transaction status to PAID
     * 
     */
    public function updateSwapTranStatus(array $args): array
    {
        $this->logger->info('WalletService.updateSwapTranStatus started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        try {
            if (empty($args)) {
                return $this->respondWithError(30101);
            }
            $transactionId = $args['transactionId'];
            
            if (!empty($transactionId) && !self::isValidUUID($transactionId)) {
                return $this->respondWithError(0000); // Invalid transaction ID provided
            }

            $results = $this->walletMapper->updateSwapTranStatus($transactionId);

            return [
                'status' => $results['status'],
                'ResponseCode' => $results['ResponseCode'],
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error in WalletService.updateSwapTranStatus", ['exception' => $e->getMessage()]);
            return $this->respondWithError(0000);  // Error occurred while update Swap transaction status
        }
    }

    
    /**
     * Swap Peer Token to BTC of Current User
     * 
     * @param args array
     * 
     * @return array with Response Object
     */
    public function swapTokens(array $args): array
    {
        $this->logger->info('WalletService.swapTokens started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        try {
            $response = $this->walletMapper->swapTokens($this->currentUserId, $args);
            if ($response['status'] === 'error') {
                return $response;
            } else {
                return [
                    'status' => 'success',
                    'ResponseCode' => $response['ResponseCode'],
                    'affectedRows' => [
                        'tokenSend' => $response['tokenSend'],
                        'tokensSubstractedFromWallet' => $response['tokensSubstractedFromWallet'],
                        'expectedBtcReturn' => $response['expectedBtcReturn'] ?? 0.0
                    ],
                ];
            }

        } catch (\Exception $e) {
            return $this->respondWithError(0000); // Failed to swap tokens
        }
    }

    /**
     * Add New Liquidity
     * 
     * @param args array
     * 
     * @return array with Response Object
     */
    public function addLiquidity(array $args): array
    {
        $this->logger->info('WalletService.addLiquidity started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }
        try {
            $response = $this->walletMapper->addLiquidity($this->currentUserId, $args);
            if ($response['status'] === 'error') {
                return $response;
            } else {
                return [
                    'status' => 'success',
                    'ResponseCode' => $response['ResponseCode'],
                    'affectedRows' => [
                        'newTokenAmount' => $response['newTokenAmount'],
                        'newBtcAmount' => $response['newBtcAmount'],
                        'newTokenPrice' => $response['newTokenPrice'] ?? 0.0
                    ],
                ];
            }
        } catch (\Exception $e) {
            return $this->respondWithError(0000); // Failed to add Liquidity
        }
    }

    
    
    /**
     * Validation for Offset and Limit values
     * 
     * @param args array
     * 
     * @return array with Response Object
     */
    protected function validateOffsetAndLimit(array $args = []): ?array
    {
        $offset = isset($args['offset']) ? (int)$args['offset'] : null;
        $limit = isset($args['limit']) ? (int)$args['limit'] : null;

        if ($offset !== null) {
            if ($offset < 0 || $offset > 200) {
                return $this->respondWithError(30203);
            }
        }

        if ($limit !== null) {
            if ($limit < 1 || $limit > 20) {  
                return $this->respondWithError(30204);
            }
        }
        return null;
    }

    
}
