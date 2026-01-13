<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Errors\PermissionDeniedException;
use Fawaz\App\Models\ShopOrder;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\PeerShopMapper;
use Fawaz\Services\TokenTransfer\Strategies\ShopTransferStrategy;
use Fawaz\Utils\ErrorResponse;

use function grapheme_strlen;

class PeerShopService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected PeerTokenMapper $peerTokenMapper,
        protected PeerShopMapper $peerShopMapper,
        protected TransactionManager $transactionManager,
        protected UserMapper $userMapper
    ) {}

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
     * Perform a shop purchase by transferring tokens to a PeerShop account.
     * 
     * This will transfer the specified amount of tokens from the current user's wallet to the PeerShop account only.  
     *
     *
     */

    public function performShopOrder(array $args): array | ErrorResponse
    {
        $this->logger->debug('PeerShopService.performShopOrder started');

        if (!$this->checkAuthentication()) {
            throw new PermissionDeniedException(60501, 'Unauthorized');
        }

        try {
            $tokenAmount = (string) $args['tokenAmount'];
            $message = isset($args['transferMessage']) ? (string) $args['transferMessage'] : null;
            
            $this->transactionManager->beginTransaction();

            $peerShop = $this->peerShopMapper->initializeWalletAccounts();
        
            if ((string) $peerShop === $this->currentUserId) {
                $this->logger->warning('Send and Receive Same Wallet Error.');
                return self::respondWithError(31202);
            }

            $receipientUserObj = $this->userMapper->loadById($peerShop);
            if (empty($receipientUserObj)) {
                $this->logger->warning('Unknown Id Exception.');
                return self::respondWithError(31007);
            }

            $currentBalance = $this->peerTokenMapper->getUserWalletBalance($this->currentUserId);
            if (empty($currentBalance) || $currentBalance < $tokenAmount) {
                $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                    'Balance' => $currentBalance,
                ]);
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }

            $requiredAmount = $this->peerTokenMapper->calculateRequiredAmount($this->currentUserId, $tokenAmount);
            if ($currentBalance < $requiredAmount) {
                $this->logger->warning('No Coverage Exception: Not enough balance to perform this action.', [
                    'senderId' => $this->currentUserId,
                    'Balance' => $currentBalance,
                    'requiredAmount' => $requiredAmount,
                ]);
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }

            // Create ShopOrder in shop order table
            $transferStrategy = new ShopTransferStrategy();

            $args['transactionoperationid'] = $transferStrategy->getOperationId();
            $args['userid'] = $this->currentUserId;
            $shopOrder = new ShopOrder($args, [], false);

            $isShopOrder = $this->peerShopMapper->createShopOrder($shopOrder);
            if (!$isShopOrder) {
                $this->transactionManager->rollback();
                $this->logger->error('PeerShopService.performShopOrder failed to create shop order');
                return self::respondWithError(00000); // Failed to create shop order
            }

            $response = $this->peerTokenMapper->transferToken(
                $this->currentUserId,
                $peerShop,
                $tokenAmount,
                $transferStrategy,
                $message
            );
            if ($response['status'] === 'error' || !$isShopOrder) {
                $this->transactionManager->rollback();
                return $response;
            } else {
                $this->logger->info('PeerShopService.performShopOrder completed successfully', ['response' => $response]);
                $this->transactionManager->commit();
                
                return self::createSuccessResponse(00000, [], true); // Product purchased successfully
            }

        } catch (\Exception $e) {
            $this->logger->error("Error in PeerShopService.performShopOrder", ['exception' => $e->getMessage()]);
            $this->transactionManager->rollback();
            return $this::respondWithError(41229); // Failed to transfer token
        }
    }

}
