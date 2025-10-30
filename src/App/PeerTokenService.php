<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Wallet;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\TokenCalculations\TokenHelper;

class PeerTokenService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(protected PeerLoggerInterface $logger, protected PeerTokenMapper $peerTokenMapper, protected UserMapper $userMapper, protected TransactionManager $transactionManager)
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
            $this->logger->debug('PeerTokenMapper.transferToken started');

            $recipientId = (string) $args['recipient'];
            $numberOfTokens = (string) $args['numberoftokens'];

            if (!self::isValidUUID($recipientId)) {
                $this->logger->warning('Incorrect recipientId Exception.', [
                    'recipientId' => $recipientId,
                ]);
                return self::respondWithError(30201);
            }
            
            $message = isset($args['message']) ? (string) $args['message'] : null;

            if ($message !== null && strlen($message) > 200) {
                $this->logger->warning('message length is too high');
                return self::respondWithError(30210);
            }
            
            if ($numberOfTokens <= 0) {
                $this->logger->warning('Incorrect Amount Exception: ZERO or less than token should not be transfer', [
                    'numberOfTokens' => $numberOfTokens,
                ]);
                return self::respondWithError(30264);
            }

            if ((string) $recipientId === $this->currentUserId) {
                $this->logger->warning('Send and Receive Same Wallet Error.');
                return self::respondWithError(31202);
            }
            
            if (!isset($args['numberoftokens']) || !is_numeric($args['numberoftokens']) || (float) $args['numberoftokens'] != $args['numberoftokens']) {
                return self::respondWithError(30264);
            }
            
            $receipientUserObj = $this->userMapper->loadById($recipientId);
            if (empty($receipientUserObj)) {
                $this->logger->warning('Unknown Id Exception.');
                return self::respondWithError(31007);
            }
            
            if (!$this->peerTokenMapper->recipientShouldNotBeFeesAccount($recipientId)) {
                $this->logger->warning('Unauthorized to send token');
                $this->transactionManager->rollback();
                return self::respondWithError(31203);
            }

            $this->transactionManager->beginTransaction();

            $currentBalance = $this->peerTokenMapper->getUserWalletBalance($this->currentUserId);
            if (empty($currentBalance) || $currentBalance < $numberOfTokens) {
                $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                    'Balance' => $currentBalance,
                ]);
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }
            
            $this->peerTokenMapper->setSenderId($this->currentUserId);
            $requiredAmount = $this->peerTokenMapper->calculateRequiredAmount($numberOfTokens);
            if ($currentBalance < $requiredAmount) {
                $this->logger->warning('No Coverage Exception: Not enough balance to perform this action.', [
                    'senderId' => $this->currentUserId,
                    'Balance' => $currentBalance,
                    'requiredAmount' => $requiredAmount,
                ]);
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }

            $response = $this->peerTokenMapper->transferToken($this->currentUserId, $recipientId, $numberOfTokens, $message, true);
            if ($response['status'] === 'error') {
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
            $this->logger->error("Error in PeerTokenService.transferToken", ['exception' => $e->getMessage()]);
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
            
            $this->logger->debug('PeerTokenMapper.swapTokens started');

            if (empty($args['btcAddress'])) {
                $this->logger->warning('BTC Address required');
                return self::respondWithError(31204);
            }
            $btcAddress = $args['btcAddress'];

            if (!$this->peerTokenMapper->isValidBTCAddress($btcAddress)) {
                $this->logger->warning('Invalid btcAddress .', [
                    'btcAddress' => $btcAddress,
                ]);
                return self::respondWithError(31204);
            }

            if (!isset($args['password']) && empty($args['password'])) {
                $this->logger->warning('Password required');
                return self::respondWithError(30237);
            }

            $user = $this->userMapper->loadById($this->currentUserId);
            $password = $args['password'];
            if (!$this->validatePasswordMatch($password, $user->getPassword())) {
                return self::respondWithError(31001);
            }

            $this->peerTokenMapper->initializeLiquidityPool();

            if (!$this->peerTokenMapper->validateFeesWalletUUIDs()) {
                return self::respondWithError(41227);
            }
            
            if (!isset($args['numberoftokens']) || !is_numeric($args['numberoftokens']) || (string) $args['numberoftokens'] != $args['numberoftokens']) {
                return self::respondWithError(30264);
            }
            $numberoftokensToSwap = (string) $args['numberoftokens'];

            $this->transactionManager->beginTransaction();
            
            $currentBalance = $this->peerTokenMapper->getUserWalletBalance($this->currentUserId);

            if (empty($currentBalance) || $currentBalance < $numberoftokensToSwap) {
                $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                    'Balance' => $currentBalance,
                ]);
                return self::respondWithError(51301);
            }

            $peerTokenBTCPrice = $this->peerTokenMapper->getTokenPriceValue();

            if (!$peerTokenBTCPrice) {
                $this->logger->error('Peer/BTC Price is NULL');
                return self::respondWithError(41203);
            }

                
            // Get EUR/BTC price
            $btcPrice = $this->peerTokenMapper->getOrUpdateBitcoinPrice();
            if (empty($btcPrice)) {
                $this->logger->error('Empty EUR/BTC Price');
                return self::respondWithError(41203);
            }
            $peerTokenEURPrice = TokenHelper::calculatePeerTokenEURPrice($btcPrice, $peerTokenBTCPrice);

            if (TokenHelper::mulRc($peerTokenEURPrice, $numberoftokensToSwap) < 10) {
                $this->logger->warning('Incorrect Amount Exception: Price should be above 10 EUROs', [
                    'btcPrice' => $btcPrice,
                    'tokenBtc' => TokenHelper::mulRc($peerTokenEURPrice, $numberoftokensToSwap),
                    'peerTokenBTCPrice' => $peerTokenBTCPrice,
                    'peerTokenEURPrice' => $peerTokenEURPrice,
                    'numberoftokens' => $numberoftokensToSwap,
                    'Balance' => $currentBalance,
                ]);
                return self::respondWithError(30271);
            }
            $message = isset($args['message']) ? (string) $args['message'] : null;

            
            $this->peerTokenMapper->setSenderId($this->currentUserId);
            $requiredAmount = $this->peerTokenMapper->calculateRequiredAmount($numberoftokensToSwap);
            if ($currentBalance < $requiredAmount) {
                $this->logger->warning('No Coverage Exception: Not enough balance to perform this action.', [
                    'senderId' => $this->currentUserId,
                    'Balance' => $currentBalance,
                    'requiredAmount' => $requiredAmount,
                ]);
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }

            $response = $this->peerTokenMapper->swapTokens($this->currentUserId, $btcAddress, $numberoftokensToSwap, $message);
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


    
    /**
     * validate password.
     *
     * @param $inputPassword string
     * @param $hashedPassword string
     * 
     * @return bool value
     */
    private function validatePasswordMatch(?string $inputPassword, string $hashedPassword): bool
    {
        if (empty($inputPassword) || empty($hashedPassword)) {
            $this->logger->warning('Password or hash cannot be empty');
            return false;
        }

        try {
            return password_verify($inputPassword, $hashedPassword);
        } catch (\Throwable $e) {
            $this->logger->error('Password verification error', ['exception' => $e]);
            return false;
        }
    }
    

}
