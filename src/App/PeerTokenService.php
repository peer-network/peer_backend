<?php

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
                return self::respondWithError(30203);
            }
        }

        if ($limit !== null) {
            if ($limit < 1 || $limit > 20) {  
                return self::respondWithError(30204);
            }
        }
        return null;
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
            return self::respondWithError(60501);
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
            return self::respondWithError(41226);  // Error occurred while retrieving transaction history
        }

    }


}