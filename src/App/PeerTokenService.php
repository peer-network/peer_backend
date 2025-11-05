<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Wallet;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;

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


}
