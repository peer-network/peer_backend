<?php
declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Wallet;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;

class PeerTokenService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(protected LoggerInterface $logger, protected PeerTokenMapper $peerTokenMapper)
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