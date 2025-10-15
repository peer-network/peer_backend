<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Wallet;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;

class PeerTokenService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(protected PeerLoggerInterface $logger, protected PeerTokenMapper $peerTokenMapper, protected TransactionManager $transactionManager)
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
     */

    public function transferToken(array $args): array
    {
        $this->logger->debug('WalletService.transferToken started');

        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }
        try {
            $this->transactionManager->beginTransaction();
            $response = $this->peerTokenMapper->transferToken($this->currentUserId, $args);
            if ($response['status'] === 'error') {
                $this->logger->error('PeerTokenService.transferToken failed', ['error' => $response['ResponseCode']]);
                $this->transactionManager->rollback();
                return $response;
            } else {
                $this->logger->info('PeerTokenService.transferToken completed successfully', ['response' => $response]);
                $this->transactionManager->commit();
                return $this::createSuccessResponse(
                    11211,
                    [
                        'tokenSend'                  => $response['tokenSend'],
                        'tokensSubstractedFromWallet' => $response['tokensSubstractedFromWallet'],
                        'createdat'                  => $response['createdat'] ?? '',
                    ],
                    false // no counter needed for associative array
                );

            }

        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            return $this::respondWithError(41229); // Failed to transfer token
        }
    }

    /**
     * Get transcation history with Filter
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
        $this->logger->debug('PeerTokenService.transactionsHistory started');

        try {
            $results = $this->peerTokenMapper->getTransactions($this->currentUserId, $args);

            return $this::createSuccessResponse(
                (int)$results['ResponseCode'],
                $results['affectedRows'],
                false // no counter needed for existing data
            );

        } catch (\Exception $e) {
            $this->logger->error("Error in PeerTokenService.transactionsHistory", ['exception' => $e->getMessage()]);
            throw new \RuntimeException("Database error while fetching transactions: " . $e->getMessage());
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

            return $this::createSuccessResponse(
                (int) $results['ResponseCode'],
                $results['affectedRows'],
                false
            );
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
                return $this::createSuccessResponse(
                    (int) $results['ResponseCode'],
                    $results['affectedRows'],
                    false
                );
            }
        } catch (\Exception $e) {
            $this->logger->error("Error in WalletService.getTokenPrice", ['exception' => $e->getMessage()]);
            return $this->respondWithError(41232);
        }
    }


    
    /**
     * Swap Peer Token to BTC of Current User
     * 
     */
    public function swapTokens(array $args): array
    {
        $this->logger->info('WalletService.swapTokens started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }

        try {
            $this->transactionManager->beginTransaction();
            $response = $this->peerTokenMapper->swapTokens($this->currentUserId, $args);
            if ($response['status'] === 'error') {
                $this->transactionManager->rollback();
                return $response;
            } else {
                $this->transactionManager->commit();
                
                return $this::createSuccessResponse(
                    (int) $response['ResponseCode'],
                    $response['affectedRows'],
                    false
                );
            }

        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            return $this->respondWithError(41231);
        }
    }


    

    /**
     * Add New Liquidity
     * 
     */
    public function addLiquidity(array $args): array
    {
        $this->logger->info('WalletService.addLiquidity started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
        }
        try {
            $this->transactionManager->beginTransaction();
            $response = $this->peerTokenMapper->addLiquidity($this->currentUserId, $args);
            if ($response['status'] === 'error') {
                $this->transactionManager->rollback();
                return $response;
            } else {
                $this->transactionManager->commit();

                return $this::createSuccessResponse(
                    (int) $response['ResponseCode'],
                    $response['affectedRows'],
                    false
                );
            }
        } catch (\Exception $e) {
            $this->transactionManager->rollback();
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
            $this->transactionManager->beginTransaction();

            $results = $this->peerTokenMapper->updateSwapTranStatus($transactionId);

            if ($results['status'] === 'error') {
                $this->transactionManager->rollback();
                return $results;
            } else {
                $this->transactionManager->commit();

                return $this::createSuccessResponse(
                    (int) $results['ResponseCode'],
                    $results['affectedRows'],
                    false
                );
            }

        } catch (\Exception $e) {
            $this->transactionManager->rollback();
            $this->logger->error("Error in WalletService.updateSwapTranStatus", ['exception' => $e->getMessage()]);
            return $this->respondWithError(41230);
        }
    }

    

}
