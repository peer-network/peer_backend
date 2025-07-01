<?php

namespace Fawaz\App;

use Fawaz\App\Wallet;
use Fawaz\Database\PeerTokenMapper;
use Psr\Log\LoggerInterface;

class PeerTokenService
{
    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected PeerTokenMapper $peerTokenMapper)
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
    

    /**
     * Make Transfer token to receipients
     * 
     * 
     */

    public function transferToken(array $args): array
    {
        $this->logger->info('WalletService.transferToken started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }
        try {
            $response = $this->peerTokenMapper->transferToken($this->currentUserId, $args);
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
            return $this->respondWithError(41229); // Failed to transfer token
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

        try {
            $results = $this->peerTokenMapper->getTransactions($this->currentUserId, $args);

            return [
                'status' => 'success',
                'ResponseCode' => $results['ResponseCode'],
                'affectedRows' => $results['affectedRows']
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error in WalletService.transactionsHistory", ['exception' => $e->getMessage()]);
            return $this->respondWithError(41226);  // Error occurred while retrieving transaction history
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
            $results = $this->peerTokenMapper->getLiquidityPoolHistory($this->currentUserId, $offset, $limit);

            return [
                'status' => 'success',
                'ResponseCode' => $results['ResponseCode'],
                'affectedRows' => $results['affectedRows']
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error in WalletService.getLiquidityPoolHistory", ['exception' => $e->getMessage()]);
            return $this->respondWithError(41226);  // Error occurred while retrieving Liquidity Pool transaction history
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
            $results = $this->peerTokenMapper->getTokenPrice();
            if ($results['status'] === 'error') {
                return $results;
            } else {
                return [
                    'status' => 'success',
                    'ResponseCode' => $results['ResponseCode'],
                    'currentTokenPrice' => $results['currentTokenPrice'],
                    'updatedAt' => $results['updatedAt'],
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error("Error in WalletService.getTokenPrice", ['exception' => $e->getMessage()]);
            return $this->respondWithError(41232);
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
            $response = $this->peerTokenMapper->swapTokens($this->currentUserId, $args);
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
            return $this->respondWithError(41231); // Failed to swap tokens
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
            $response = $this->peerTokenMapper->addLiquidity($this->currentUserId, $args);
            if ($response['status'] === 'error') {
                return $response;
            } else {
                return [
                    'status' => 'success',
                    'ResponseCode' => $response['ResponseCode'],
                    'affectedRows' => [
                        'newTokenAmount' => $response['newTokenAmount'],
                        'newBtcAmount' => $response['newBtcAmount'],
                        'newTokenPrice' => $response['newTokenPrice'] ?? ""
                    ],
                ];
            }
        } catch (\Exception $e) {
            return $this->respondWithError(41228); // Failed to add Liquidity
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
                return $this->respondWithError(30242); // Invalid transaction ID provided
            }

            $results = $this->peerTokenMapper->updateSwapTranStatus($transactionId);

            if ($results['status'] === 'error') {
                return $results;
            } else {
                return [
                    'status' => 'success',
                    'ResponseCode' => $results['ResponseCode'],
                    'affectedRows' => [
                        'swapid' => $results['affectedRows']['swapid'],
                        'transactionid' => $results['affectedRows']['transactionid'],
                        'transactiontype' => $results['affectedRows']['transactiontype'],
                        'senderid' => $results['affectedRows']['senderid'],
                        'tokenamount' => $results['affectedRows']['tokenamount'],
                        'btcamount' => $results['affectedRows']['btcamount'],
                        'status' => $results['affectedRows']['status'],
                        'message' => $results['affectedRows']['message'],
                        'createdat' => $results['affectedRows']['createdat'],
                    ]
                ];
            }

        } catch (\Exception $e) {
            $this->logger->error("Error in WalletService.updateSwapTranStatus", ['exception' => $e->getMessage()]);
            return $this->respondWithError(41230);  // Error occurred while update Swap transaction status
        }
    }

    

}
