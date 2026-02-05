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
use Fawaz\Database\Interfaces\InteractionsPermissionsMapper;
use Fawaz\Database\PeerShopMapper;
use Fawaz\Services\TokenTransfer\Strategies\ShopTransferStrategy;
use Fawaz\Database\Interfaces\ShopOrderPermissionsMapper;
use Fawaz\Database\ShopOrderPermissionsMapperImpl;
use Fawaz\Services\ContentFiltering\Specs\SpecTypes\User\SystemUserSpec;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringCases;
use Fawaz\Services\ContentFiltering\Types\ContentType;
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
        protected InteractionsPermissionsMapper $interactionsPermissionsMapper,
        protected UserMapper $userMapper,
        protected ShopOrderPermissionsMapper $shopOrderPermissionsMapper
    ) {
    }

    public function setCurrentUserId(string $userId): void
    {
        $this->currentUserId = $userId;
    }

    private function checkAuthentication(): bool
    {
        if ($this->currentUserId === null) {
            $this->logger->error('PeerShopService.checkAuthentication: Unauthorized access attempt');
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
            $this->logger->error('PeerShopService.performShopOrder: Unauthorized access attempt');
            throw new PermissionDeniedException(60501, 'Unauthorized');
        }

        try {
            $tokenAmount = (string) $args['tokenAmount'];
            $message = isset($args['transferMessage']) ? (string) $args['transferMessage'] : null;
            
            $peerShop = $this->peerShopMapper->initializeWalletAccounts();

            $receipientUserObj = $this->userMapper->loadById($peerShop);
            if (empty($receipientUserObj)) {
                $this->logger->error('PeerShopService.performShopOrder: Peer Shop Account not found', ['peerShop' => $peerShop]);
                return self::respondWithError(41223);
            }

            $contentFilterCase = ContentFilteringCases::searchById;

            $systemUserSpec = new SystemUserSpec(
                $contentFilterCase,
                ContentType::user
            );

            $specs = [
                $systemUserSpec
            ];

            if ($this->interactionsPermissionsMapper->isInteractionAllowed($specs, $this->currentUserId) === false) {
                $this->logger->error('PeerShopService.performShopOrder: Interaction not allowed', ['currentUserId' => $this->currentUserId]);
                return $this::respondWithError(31205, ['Current User Id' => $this->currentUserId]);
            }
            $this->transactionManager->beginTransaction();

        
            if ((string) $peerShop === $this->currentUserId) {
                $this->logger->error('PeerShopService.performShopOrder: Send and Receive Same Wallet Error', ['currentUserId' => $this->currentUserId]);
                return self::respondWithError(31202);
            }

            $currentBalance = $this->peerTokenMapper->getUserWalletBalance($this->currentUserId);
            if (empty($currentBalance) || $currentBalance < $tokenAmount) {
                $this->logger->error('PeerShopService.performShopOrder: Insufficient balance', [
                    'Balance' => $currentBalance,
                ]);
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }

            $requiredAmount = $this->peerTokenMapper->calculateRequiredAmount($this->currentUserId, $tokenAmount);
            if ($currentBalance < $requiredAmount) {
                $this->logger->error('PeerShopService.performShopOrder: Not enough balance to perform this action', [
                    'senderId' => $this->currentUserId,
                    'Balance' => $currentBalance,
                    'requiredAmount' => $requiredAmount,
                ]);
                $this->transactionManager->rollback();
                return self::respondWithError(51301);
            }

            // Create ShopOrder in shop order table
            $transferStrategy = new ShopTransferStrategy();

            $senderUserObj = $this->userMapper->loadById($this->currentUserId);

            $response = $this->peerTokenMapper->transferToken(
                $tokenAmount,
                $transferStrategy,
                $senderUserObj,
                $receipientUserObj,
                $message
            );

            $args['transactionid'] = $transferStrategy->getTransactionId();
            $args['userid'] = $this->currentUserId;
            $shopOrder = new ShopOrder($args, [], false);

            $isShopOrder = $this->peerShopMapper->createShopOrder($shopOrder);
            if (!$isShopOrder) {
                $this->transactionManager->rollback();
                $this->logger->error('PeerShopService.performShopOrder failed to create shop order');
                return self::respondWithError(41223); // Failed to create shop order
            }
            
            if ($response['status'] === 'error' || !$isShopOrder) {
                $this->transactionManager->rollback();
                $this->logger->error('PeerShopService.performShopOrder: Token transfer failed', ['response' => $response]);
                return $response;
            } else {
                $this->logger->info('PeerShopService.performShopOrder completed successfully', ['response' => $response]);
                $this->transactionManager->commit();
                
                return self::createSuccessResponse(12201, [], true); // Product purchased successfully
            }

        } catch (\Exception $e) {
            $this->logger->error("Error in PeerShopService.performShopOrder", ['exception' => $e->getMessage()]);
            $this->transactionManager->rollback();
            return $this::respondWithError(41223); // Failed to transfer token
        }
    }


    /**
     * Get Shop Order Details for selected OperationId.
     */
    public function shopOrderDetails(array $args): array
    {
        $this->logger->info('PeerShopService.shopOrderDetails started');

        if (!$this->checkAuthentication()) {
            $this->logger->error('PeerShopService.shopOrderDetails: Unauthorized access attempt');
            throw new PermissionDeniedException(60501, 'Unauthorized');
        }

        try {
            $transactionId = (string)($args['transactionId'] ?? $args['transactionId'] ?? '');

            if ($transactionId === '') {
                $this->logger->error('PeerShopService.shopOrderDetails: Missing transactionId');
                return self::respondWithError(30101);
            }

            $order = $this->peerShopMapper->getShopOrderDetails($transactionId);

            if (empty($order)) {
                $this->logger->error('PeerShopService.shopOrderDetails: Shop order not found', ['transactionId' => $transactionId]);
                return self::respondWithError(22101); // Shop Order not found
            }

            $orderOwnerId = (string)($order['userid'] ?? '');
            $peerShopAccount = (string)($_ENV['PEER_SHOP'] ?? '');

            $isAllowed = $this->shopOrderPermissionsMapper->canAccessShopOrder(
                $this->currentUserId,
                [$orderOwnerId, $peerShopAccount]
            );

            if (!$isAllowed) {
                $this->logger->error('PeerShopService.shopOrderDetails: Unauthorized access attempt', [
                    'currentUserId' => $this->currentUserId,
                    'orderOwnerId' => $orderOwnerId,
                ]);
                return self::respondWithError(60501);
            }

            $orderDetails = [
                'shopOrderId' => (string)($order['shoporderid'] ?? ''),
                'shopItemId' => (string)($order['shopitemid'] ?? ''),
                'shopItemSpecs' => [
                    'size' => (string)($order['size'] ?? ''),
                ],
                'deliveryDetails' => [
                    'name' => (string)($order['name'] ?? ''),
                    'email' => (string)($order['email'] ?? ''),
                    'addressline1' => (string)($order['addressline1'] ?? ''),
                    'addressline2' => $order['addressline2'] ?? null,
                    'city' => (string)($order['city'] ?? ''),
                    'zipcode' => (string)($order['zipcode'] ?? ''),
                    'country' => (string)($order['country'] ?? ''),
                ],
                'createdat' => (string)($order['createdat'] ?? ''),
            ];

            // Shop Order details fetched successfully
            return self::createSuccessResponse(12202, $orderDetails); 

        } catch (\Exception $e) {
            $this->logger->error("Error in PeerShopService.shopOrderDetails", ['exception' => $e->getMessage()]);
            throw new \RuntimeException("Database error while fetching transactions: " . $e->getMessage());
        }
    }

}
