<?php

declare(strict_types=1);

namespace Fawaz\App;

use Fawaz\App\Wallet;
use Fawaz\Database\WalletMapper;
use Fawaz\Utils\PeerLoggerInterface;
use Exception;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Services\TokenTransfer\Strategies\AdsTransferStrategy;
use Fawaz\Services\TokenTransfer\Strategies\TransferStrategy;

class WalletService
{
    use ResponseHelper;
    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected WalletMapper $walletMapper,
        protected PeerTokenMapper $peerTokenMapper,
        protected TransactionManager $transactionManager
    ) {
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

    public function fetchWalletById(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $userId = $this->currentUserId;
        $postId = $args['postid'] ?? null;
        $fromId = $args['fromid'] ?? null;

        if ($postId === null && $fromId === null && !self::isValidUUID($userId)) {
            return $this::respondWithError(30102);
        }

        if ($postId !== null && !self::isValidUUID($postId)) {
            return $this::respondWithError(30209);
        }

        if ($fromId !== null && !self::isValidUUID($fromId)) {
            return $this::respondWithError(30105);
        }

        $this->logger->debug("WalletService.fetchWalletById started");

        try {
            $wallets = $this->walletMapper->loadWalletById($this->currentUserId, $args);

            if ($wallets === false) {
                return $this::respondWithError(41216);
            }

            $walletData = array_map(
                static fn (Wallet $wallet) => $wallet->getArrayCopy(),
                $wallets
            );

            $this->logger->info("WalletService.fetchWalletById successfully fetched wallets", [
                'count' => count($walletData),
            ]);

            $success = [
                'status' => 'success',
                'counter' => count($walletData),
                'ResponseCode' => "11209",
                'affectedRows' => $walletData
            ];

            return $success;

        } catch (Exception $e) {
            $this->logger->error("Error occurred in WalletService.fetchWalletById", [
                'error' => $e->getMessage(),
                'args' => $args,
            ]);
            return $this::respondWithError(40301);
        }
    }

    public function callFetchWinsLog(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];
        $day = $args['day'] ?? 'D0';

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this::respondWithError(30105);
        }

        return $this->walletMapper->fetchWinsLog($this->currentUserId, 'win', $args);
    }

    public function callFetchPaysLog(?array $args = []): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'W0', 'M0', 'Y0'];
        $day = $args['day'] ?? 'D0';

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this::respondWithError(30105);
        }

        return $this->walletMapper->fetchWinsLog($this->currentUserId, 'pay', $args);
    }

    public function callGlobalWins(): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        return $this->walletMapper->callGlobalWins();
    }

    public function callGemster(): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        return $this->walletMapper->getTimeSorted();
    }

    public function callGemsters(string $day = 'D0'): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7', 'W0', 'M0', 'Y0'];

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this::respondWithError(30105);
        }

        $gemsters = $this->walletMapper->getTimeSortedMatch($day);

        if (isset($gemsters['affectedRows']['data'])) {
            $winstatus = $gemsters['affectedRows']['data'][0];
            unset($gemsters['affectedRows']['data'][0]);

            $userStatus = array_values($gemsters['affectedRows']['data']);

            $affectedRows = [
                'winStatus' => $winstatus ?? [],
                'userStatus' => $userStatus,
            ];

            return [
                'status' => $gemsters['status'],
                'counter' => $gemsters['counter'] ?? 0,
                'ResponseCode' => $gemsters['ResponseCode'],
                'affectedRows' => $affectedRows
            ];
        }
        return [
            'status' => $gemsters['status'],
            'counter' => 0,
            'ResponseCode' => $gemsters['ResponseCode'],
            'affectedRows' => []
        ];
    }

    public function loadLiquidityById(string $userId): array
    {
        $this->logger->debug('WalletService.loadLiquidityById started');

        try {
            $results = $this->walletMapper->loadLiquidityById($userId);

            return [
                'status' => 'success',
                'ResponseCode' => "11204",
                'currentliquidity' => $results,
            ];
        } catch (\Exception $e) {
            return $this::respondWithError(41204);
        }
    }

    public function getUserWalletBalance(string $userId): float
    {
        $this->logger->debug('WalletService.getUserWalletBalance started');

        try {
            return $this->walletMapper->getUserWalletBalance($userId);
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function deductFromWallet(string $userId, ?array $args = []): ?array
    {
        $this->logger->debug('WalletService.deductFromWallet started');

        try {
            $this->transactionManager->beginTransaction();
            $response = $this->walletMapper->deductFromWallets($userId, $args);
            if ($response['status'] === 'success') {
                $this->transactionManager->commit();
                return $response;
            } else {
                $this->transactionManager->rollBack();
                return $response;
            }

        } catch (Exception $e) {
            $this->transactionManager->rollBack();
            return $this::respondWithError(40301);
        }
    }

    public function callUserMove(): ?array
    {
        $this->logger->debug('WalletService.callUserMove started');

        try {
            $response = $this->walletMapper->callUserMove($this->currentUserId);
            return $this::createSuccessResponse(
                $response['ResponseCode'],
                $response['affectedRows'],
                false // no counter needed for existing data
            );


        } catch (Exception $e) {
            return $this::respondWithError(41205);
        }
    }

    /**
     * Transfer Tokens from User Wallet for Advertisement Actions
     * Add logwins entry
     */
    public function performPayment(string $userId, TransferStrategy $transferStrategy, ?array $args = []): ?array
    {
        $this->logger->debug('WalletService.performPayment started');

        try {
            $postId = $args['postid'] ?? null;
            $art = $args['art'] ?? null;
            $prices = ConstantsConfig::tokenomics()['ACTION_TOKEN_PRICES'];
            $actions = ConstantsConfig::wallet()['ACTIONS'];

            $mapping = [
                2 => ['price' => $prices['like'], 'whereby' => $actions['LIKE'], 'text' => ''],
                3 => ['price' => $prices['dislike'], 'whereby' => $actions['DISLIKE'], 'text' => ''],
                4 => ['price' => $prices['comment'], 'whereby' => $actions['COMMENT'], 'text' => ''],
                5 => ['price' => $prices['post'], 'whereby' => $actions['POST'], 'text' => ''],
                6 => ['price' => $prices['advertisementBasic'], 'whereby' => $actions['POSTINVESTBASIC'], 'text' => ''],
                7 => ['price' => $prices['advertisementPinned'], 'whereby' => $actions['POSTINVESTPREMIUM'], 'text' => ''],
            ];

            if (!isset($mapping[$art])) {
                $this->logger->warning('Invalid art type provided.', ['art' => $art]);
                return self::respondWithError(30105);
            }

            $price = (!empty($args['price']) && (int)$args['price']) ? (int)$args['price'] : $mapping[$art]['price'];
            $whereby = $mapping[$art]['whereby'];
            $text = $mapping[$art]['text'];

            $currentBalance = $this->getUserWalletBalance($userId);
            $price = (string) $price;

            // Determine required amount per fee policy mode
            $mode = $transferStrategy->getFeePolicyMode();
            $requiredAmount = $this->peerTokenMapper->calculateRequiredAmountByMode($userId, (string)$price, $mode);
            if ($currentBalance < $requiredAmount) {
                $this->logger->warning('No Coverage Exception: Not enough balance to perform this action.', [
                    'senderId' => $this->currentUserId,
                    'Balance' => $currentBalance,
                    'requiredAmount' => $requiredAmount,
                ]);
                return self::respondWithError(51301);
            }

            [$burnWallet, $peerWallet, $btcpool] = $this->peerTokenMapper->initializeLiquidityPool();
            $fromId = $args['fromid'] ?? $peerWallet;

            $args = [
                'postid' => $postId,
                'fromid' => $fromId,
                'gems' => 0.0,
                'numbers' => -abs((float)$price),
                'whereby' => $whereby,
                'createdat' => new \DateTime()->format('Y-m-d H:i:s.u'),
            ];

            $response = $this->peerTokenMapper->transferToken(
                $userId,
                $fromId,
                $price,
                $transferStrategy,
                $text
            );

            $args['gemid'] = $transferStrategy->getOperationId();
            $results = $this->walletMapper->insertWinToLog($userId, $args);
            if ($results === false) {
                $this->logger->error("Error occurred in performPayment.insertWinToLog", [
                    'userId' => $userId,
                    'args' => $args,
                ]);
                return self::respondWithError(41205);
            }

            if ($response['status'] === 'success') {
                return $response;
            } else {
                return $response;
            }

        } catch (Exception $e) {
            $this->logger->error('Error while paying for advertisement WalletService.performPayment', ['exception' => $e->getMessage()]);
            return $this::respondWithError(40301);
        }
    }
}
