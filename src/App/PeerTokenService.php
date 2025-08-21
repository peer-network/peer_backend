<?php

namespace Fawaz\App;

use Fawaz\App\Wallet;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;

class PeerTokenService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected PeerTokenMapper $peerTokenMapper, protected TransactionManager $transactionManager)
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


    public function transferToken(array $args): array
    {
        $this->logger->info('WalletService.transferToken started');

        if (!$this->checkAuthentication()) {
            return $this->respondWithError(60501);
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
            $this->transactionManager->rollback();
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
        $this->logger->info('PeerTokenService.transactionsHistory started');

        try {
            $results = $this->peerTokenMapper->getTransactions($this->currentUserId, $args);

            return [
                'status' => 'success',
                'ResponseCode' => $results['ResponseCode'],
                'affectedRows' => $results['affectedRows']
            ];
        }catch (\Exception $e) {
            $this->logger->error("Error in PeerTokenService.transactionsHistory", ['exception' => $e->getMessage()]);
            throw new \RuntimeException("Database error while fetching transactions: " . $e->getMessage());
        }

    }


}