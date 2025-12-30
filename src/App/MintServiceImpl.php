<?php

declare(strict_types=1);

namespace Fawaz\App;

use DateTime;
use Fawaz\App\DTO\MintLogItem;
use Fawaz\App\DTO\UncollectedGemsResult;
use Fawaz\App\Repositories\MintAccountRepository;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Database\GemsRepository;
use Fawaz\Database\Interfaces\TransactionManager;
use Fawaz\Database\MintRepository;
use Fawaz\Database\PeerTokenMapper;
use Fawaz\Database\UserActionsRepository;
use Fawaz\Database\UserMapper;
use Fawaz\Database\WalletMapper;
use Fawaz\Services\TokenTransfer\Strategies\MintTransferStrategy;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use PDO;
use Fawaz\App\DTO\GemsInTokenResult;

class MintServiceImpl implements MintService
{
    use ResponseHelper;

    protected ?string $currentUserId = null;

    public function __construct(
        protected PeerLoggerInterface $logger,
        protected MintAccountRepository $mintAccountRepository,
        protected MintRepository $mintRepository,
        protected UserMapper $userMapper,
        protected PeerTokenMapper $peerTokenMapper,
        protected UserActionsRepository $userActionsRepository,
        protected GemsRepository $gemsRepository,
        protected TransactionManager $transactionManager,
        protected WalletMapper $walletMapper,
        protected PDO $db
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
        // Admin-only: allow ADMIN and SUPER_ADMIN
        $user = $this->userMapper->loadById($this->currentUserId);
        if (!$user) {
            $this->logger->warning('User not found for admin check', ['uid' => $this->currentUserId]);
            return false;
        }
        $rolesMask = $user->getRolesmask();
        if ($rolesMask !== Role::ADMIN && $rolesMask !== Role::SUPER_ADMIN) {
            $this->logger->warning('Forbidden: admin-only endpoint', ['uid' => $this->currentUserId, 'roles' => $rolesMask]);
            return false;
        }
        return true;
    }
    
    public function listTodaysInteractions(): ?array
    {
        $this->logger->debug('WalletService.listTodaysInteractions started');

        try {
            $response = $this->userActionsRepository->listTodaysInteractions($this->currentUserId);
            return $this::createSuccessResponse(
                $response['ResponseCode'],
                $response['affectedRows'],
                false // no counter needed for existing data
            );


        } catch (\Exception $e) {
            return $this::respondWithError(41205);
        }
    }

    private static function calculateGemsInToken(UncollectedGemsResult $uncollectedGems): GemsInTokenResult {
        $totalGems = $uncollectedGems->overallTotal;
        $dailyToken = (string)(ConstantsConfig::minting()['DAILY_NUMBER_TOKEN']);

        $gemsintoken = TokenHelper::divRc($dailyToken, $totalGems);
        $bestatigungInitial = TokenHelper::mulRc($totalGems, $gemsintoken);
        return new GemsInTokenResult($totalGems, $gemsintoken, $bestatigungInitial);
    }


    private function tokensPerUser(
        UncollectedGemsResult $uncollectedGems,
        GemsInTokenResult $gemsInToken
    ): array {
        $tokenTotals = [];

        foreach ($uncollectedGems->rows as $row) {
            $userId = (string)$row->userid;
            if (!isset($args[$userId])) {
                $totalTokenNumber = TokenHelper::mulRc((string) $row->totalNumbers, $gemsInToken->gemsInToken);
                $args[$userId] = [
                    'userid' => $userId,
                    'gems' => (float)$row->totalNumbers,
                    'tokens' => $totalTokenNumber,
                    'percentage' => (float)$row->percentage,
                    'details' => []
                ];

                // Track total tokens per user in a compact internal array
                $tokenTotals[$userId] = $totalTokenNumber;
            }
        }

        return $tokenTotals;
    }

    public function distributeTokensFromGems(string $day = 'D0'): array
    {
        if (!$this->checkAuthentication()) {
            return $this::respondWithError(60501);
        }

        $dayActions = ['D0', 'D1', 'D2', 'D3', 'D4', 'D5', 'D6', 'D7'];

        // Validate entry of day
        if (!in_array($day, $dayActions, true)) {
            return $this::respondWithError(30105);
        }

        try {
            // Prevent duplicate minting for the selected period
            if ($this->mintRepository->getMintForDay($day)) {
                $this->logger->error('Mint already performed for selected period', ['day' => $day]);
                return $this::respondWithError(40301);
            }

            $this->transactionManager->beginTransaction();

            $mintid = $this->generateUUID();

            // ALL uncollected gems
            $uncollectedGems = $this->gemsRepository->fetchUncollectedGemsForMintResult($day);

            if (empty($uncollectedGems)) {
                $this->transactionManager->rollback();
                return self::createSuccessResponse(21206);
            }

            $gemsInTokenResult = $this::calculateGemsInToken(
                $uncollectedGems
            );
            
            $tokensPerUser = $this->tokensPerUser(
                $uncollectedGems,
                $gemsInTokenResult
            );

            $args = $this->transferMintTokens(
                $tokensPerUser,
                $uncollectedGems,
                $gemsInTokenResult
            );
            
            $this->mintRepository->insertMint(
                $mintid,
                new DateTime()->format('Y-m-d'),
                $gemsInTokenResult->gemsInToken
            );

            $this->gemsRepository->applyMintInfo(
                $mintid,
                $uncollectedGems,
                $args
            );

            $this->transactionManager->commit();
            return $this->createSuccessResponse(
                11208,
                [
                    'winStatus' => $gemsInTokenResult->toWinStatusArray(),
                    'userStatus' =>  array_values($args),
                    'counter' => count($args) - 1
                ],
                true,
                'counter'    
            );
        } catch(\Throwable $e) {
            $this->transactionManager->rollback();
            $this->logger->error('Error during mint distribution transfers', [
                'error' => $e->getMessage(),
            ]);
            return $this::respondWithError(40301);
        }
    }

    private function transferMintTokens(
        array $tokensPerUser,
        UncollectedGemsResult $uncollectedGems,
        GemsInTokenResult $gemsInTokenResult
    ): array {
        $mintAccount = $this->mintAccountRepository->getDefaultAccount();

        if ($mintAccount === null) {
            $this->logger->warning('No MintAccount available for distribution');
            throw new ValidationException('No MintAccount available for distribution', [40301]);
        }
        
        $args = [];

        foreach ($tokensPerUser as $recipientUserId => $amountToTransfer) {
            // Skip zero or negative amounts
            if ((float)$amountToTransfer <= 0) {
                $this->logger->error('amount to transfer is 0', [
                    'userId' => $recipientUserId,
                ]);
                throw new ValidationException('amount to transfer is 0', [40301]);
            }
            $recipient = $this->userMapper->loadById($recipientUserId); // user loadByIds!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            if ($recipient === false) {
                $this->logger->error('Recipient user not found for token transfer', [
                    'userId' => $recipientUserId,
                ]);
                throw new ValidationException('Recipient user not found for token transfer', [40301]);
            }

            $transactionStrategy = new MintTransferStrategy();


            $args[$recipientUserId]['operationId'] = $transactionStrategy->getOperationId();
            $args[$recipientUserId]['transactionId'] = $transactionStrategy->getTransactionId();

            $response = $this->peerTokenMapper->transferToken(
                (string)$amountToTransfer,
                $transactionStrategy,
                $mintAccount,
                $recipient,
                "Mint"
            );

            if (!is_array($response) || ($response['status'] ?? 'error') === 'error') {
                $this->logger->error('Mint distribution transfer failed for user', [
                    'userId' => $recipientUserId,
                    'amount' => $amountToTransfer,
                    'response' => $response,
                ]);
                throw new ValidationException('Mint distribution transfer failed', [40301]);
            }
        }

        foreach ($uncollectedGems->rows as $row) {
            $userId = (string)$row->userid;

            if (!isset($args[$userId])) {
                $totalTokenNumber = TokenHelper::mulRc((string) $row->totalNumbers, $gemsInTokenResult->gemsInToken);
                $args[$userId] = [
                    'userid' => $userId,
                    'gems' => (float)$row->totalNumbers,
                    'tokens' => $totalTokenNumber,
                    'percentage' => (float)$row->percentage,
                    'details' => []
                ];
            }

            $rowgems2token = TokenHelper::mulRc((string) $row->gems, $gemsInTokenResult->gemsInToken);

            $args[$userId]['details'][] = [
                'gemid' => (string)$row->gemid,
                'userid' => (string)$row->userid,
                'postid' => (string)$row->postid,
                'fromid' => (string)$row->fromid,
                'gems' => (float)$row->gemid,
                'numbers' => (float)$rowgems2token,
                'whereby' => (int)$row->whereby,
                'createdat' => $row->createdat
            ];

            $args[$userId]['logItem'] = new MintLogItem(
                $row->gemid,
                $args[$row->userid]['transactionId'],
                $args[$row->userid]['operationId'],
                $rowgems2token
            );
        }

        return $args;
    }

    /**
     * Get the single Mint Account row.
     */
    public function getMintAccount(): array
    {
        if (!$this->checkAuthentication()) {
            return self::respondWithError(60501);
        }

        try {
            $this->logger->debug('MintService.getMintAccount started');
            $account = $this->mintAccountRepository->getDefaultAccount();
            if (!$account) {
                $this->logger->debug('MintService.getMintAccount result is empty');
                return self::respondWithError(40401);
            }

            $this->logger->info('MintService.getMintAccount completed');
            // Map to MintAccount GraphQL type and wrap in standard success response (no counter)
            return $this::createSuccessResponse(00000, $account->getArrayCopy(), false);
        } catch (\Throwable $e) {
            $this->logger->error('MintService.getMintAccount failed', [
                'error' => $e->getMessage(),
            ]);
            return self::respondWithError(40301);
        }
    }
}
