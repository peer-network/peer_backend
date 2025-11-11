<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Wallet;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Services\TokenTransfer\Strategies\DefaultTransferStrategy;
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
            
            // Strict numeric validation for decimals (e.g., "1", "1.0", "0.25")
            $numRaw = (string)($args['numberoftokens'] ?? '');
            // Accepts unsigned decimal numbers with optional fractional part
            $isStrictDecimal = $numRaw !== '' && preg_match('/^(?:\d+)(?:\.\d+)?$/', $numRaw) === 1;
            if (!$isStrictDecimal) {
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
            
            $requiredAmount = $this->peerTokenMapper->calculateRequiredAmount($this->currentUserId, $numberOfTokens);
            if ($currentBalance < $requiredAmount) {
                $this->logger->warning('No Coverage Exception: Not enough balance to perform this action.', [
                    'senderId' => $this->currentUserId,
                    'Balance' => $currentBalance,
                    'requiredAmount' => $requiredAmount,
                ]);
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }

            $transferStrategy = new DefaultTransferStrategy();

            $response = $this->peerTokenMapper->transferToken(
                $this->currentUserId, 
                $recipientId, 
                $numberOfTokens, 
                $transferStrategy,
                $message, 
                true
            );
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
