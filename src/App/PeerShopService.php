<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Errors\PermissionDeniedException;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Database\UserMapper;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\PeerShopMapper;
use Fawaz\Services\TokenTransfer\Strategies\ShopTransferStrategy;

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

    public function performShopOrder(array $args): array
    {
        $this->logger->debug('PeerShopService.performShopOrder started');

        if (!$this->checkAuthentication()) {
            throw new PermissionDeniedException(60501, 'Unauthorized');
        }

        try {
            $tokenAmount = (string) $args['tokenAmount'];

            if(!isset($args['tokenAmount']) || empty($tokenAmount) || !is_numeric($tokenAmount) || (float)$tokenAmount <= 0) {
                $this->logger->warning('Amount Missing or Invalid Amount.');
                return self::respondWithError(30264);
            }

            $userConfig  = ConstantsConfig::user();
            $inputConfig = ConstantsConfig::input();
            $message = isset($args['transferMessage']) ? (string) $args['transferMessage'] : null;
            if ($message !== null) {
                $messageConfig = $userConfig['TRANSFER_MESSAGE'];

                $maxLength      = (int) $messageConfig['MAX_LENGTH'];
                $controlPattern = '/'.$inputConfig['FORBID_CONTROL_CHARS_PATTERN'].'/u';
                $urlPattern     = '/'.$messageConfig['PATTERN_URL'].'/iu';

                if (grapheme_strlen($message) > $maxLength) {
                    $this->logger->warning('Transfer message length is too high', [
                        'maxLength' => $maxLength,
                    ]);
                    return self::respondWithError(30270);
                }
                if (preg_match($controlPattern, $message) === 1) {
                    $this->logger->warning('Transfer message contains control characters');
                    return self::respondWithError(30271);
                }
                if (preg_match($urlPattern, $message) === 1) {
                    $this->logger->warning('Transfer message contains URL/link');
                    return self::respondWithError(30271);
                }
            }

            $transferConfig = $userConfig['TRANSACTION'];
            $minAmount = (float) $transferConfig['MIN_AMOUNT'];
            $maxDecimals = (int) $transferConfig['MAX_DECIMALS'];

            $parts = explode('.', (string) $tokenAmount);

            if (isset($parts[1]) && strlen($parts[1]) > $maxDecimals) {
                return self::respondWithError(30264);
            }

            if ((float) $tokenAmount < $minAmount) {
                $this->logger->warning('Incorrect Amount Exception: less than minimum transfer amount', [
                    'tokenAmount' => $tokenAmount,
                    'minAmount'      => $minAmount,
                ]);
                return self::respondWithError(30264);
            }

            // Strict numeric validation for decimals (e.g., "1", "1.0", "0.25")
            $numRaw = (string)($args['tokenAmount']);
            // Accepts unsigned decimal numbers with optional fractional part
            $isStrictDecimal = $numRaw !== '' && preg_match('/^(?:\d+)(?:\.\d+)?$/', $numRaw) === 1;
            if (!$isStrictDecimal) {
                return self::respondWithError(30264);
            }
            
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

            $transferStrategy = new ShopTransferStrategy();

            $response = $this->peerTokenMapper->transferToken(
                $this->currentUserId,
                $peerShop,
                $tokenAmount,
                $transferStrategy,
                $message
            );
            if ($response['status'] === 'error') {
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
