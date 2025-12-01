<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\App\Models\Transaction;
use Fawaz\App\Repositories\TransactionRepository;
use Fawaz\App\User;
use PDO;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use Fawaz\Utils\PeerLoggerInterface;
use RuntimeException;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\TokenTransfer\Strategies\TransferStrategy;
use Fawaz\Services\TokenTransfer\Strategies\DefaultTransferStrategy;
use Fawaz\Services\TokenTransfer\Fees\FeePolicyMode;

class PeerTokenMapper
{
    use ResponseHelper;
    private string $burnWallet;
    private string $peerWallet;
    private string $btcpool;
    private string $senderId;
    private string $inviterId;

    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db, protected LiquidityPool $pool, protected WalletMapper $walletMapper, protected UserMapper $userMapper)
    {
    }

    /**
     * Loads and validates the liquidity pool and FEE's wallets.
     *
     * @throws \RuntimeException if accounts are missing or invalid
     */
    public function initializeLiquidityPool(): array
    {
        $accounts = $this->pool->returnAccounts();
        if (($accounts['status'] ?? '') === 'error') {
            throw new \RuntimeException("Failed to load pool accounts");
        }

        $data = $accounts['response'] ?? [];
        if (!isset($data['pool'], $data['burn'], $data['peer'])) {
            throw new \RuntimeException("Liquidity pool wallets incomplete");
        }

        $this->burnWallet = $data['burn'];
        $this->peerWallet = $data['peer'];
        $this->btcpool = $data['btcpool'];

        return [
            $this->burnWallet,
            $this->peerWallet,
            $this->btcpool
        ];
    }


    /**
     * Receipient should not be any of the fee wallets.
     */
    public function recipientShouldNotBeFeesAccount(string $recipientId): bool
    {
        $this->initializeLiquidityPool();

        return $recipientId !== $this->burnWallet
            && $recipientId !== $this->peerWallet
            && $recipientId !== $this->btcpool;
    }

    /**
     * get LP account tokens amount.
     *
     */
    public function getLpToken(): string
    {
        $this->logger->debug("PeerTokenMapper.getLpToken started");

        $query = "SELECT * from wallett WHERE userid = :userId";

        $accounts = $this->pool->returnAccounts();
        $liqpool = $accounts['response'] ?? null;
        $poolWallet = $liqpool['pool'] ?? null;

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':userId', $poolWallet, \PDO::PARAM_STR);
            $stmt->execute();
            $walletInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

            return (string) $walletInfo['liquidity'];
        } catch (\PDOException $e) {
            $this->logger->error(
                "PeerTokenMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "PeerTokenMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage()
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        }
    }

    /*
     * Validate fees wallet UUID.
     *
     * @param $inputPassword string
     * @param $hashedPassword string
     *
     * @return bool value
     */
    public function validateFeesWalletUUIDs(): bool
    {
        return self::isValidUUID($this->burnWallet)
            && self::isValidUUID($this->peerWallet)
            && self::isValidUUID($this->btcpool);
    }

    /**
     *
     */
    public function setSenderId(string $senderId): void
    {
        $this->senderId = $senderId;
    }

    /**
     * Includes fees while calculating required amount for transfer.
     */
    public function calculateRequiredAmount(string $senderId, string $numberOfTokens): string
    {
        $this->senderId = $senderId;

        [$peerFee, $burnFee, $inviteFee] = $this->getEachFeesAmount();

        return TokenHelper::calculateTokenRequiredAmount($numberOfTokens, $peerFee, $burnFee, $inviteFee);
    }

    /**
     * Each Fees Amount
     */
    private function getEachFeesAmount(): array
    {
        $fees = ConstantsConfig::tokenomics()['FEES_STRING'];
        $peerFee = (string) $fees['PEER'];
        $burnFee = (string) $fees['BURN'];

        // Check for Inviter Fee
        $inviteFee = '0';
        $inviterId = $this->userMapper->getInviterID($this->senderId);

        if (!empty($inviterId) && self::isValidUUID($inviterId)) {
            $this->inviterId = $inviterId;
            $inviteFee = (string) $fees['INVITATION'];
        }
        return [$peerFee, $burnFee, $inviteFee];
    }

    /**
     * Each Fees Amount Calculation.
     */
    public function calculateEachFeesAmount(string $numberOfTokens): array
    {
        [$peerFee, $burnFee, $inviteFee] = $this->getEachFeesAmount();

        return [
            TokenHelper::mulRc($numberOfTokens, $peerFee), // Peer Fee Amount
            TokenHelper::mulRc($numberOfTokens, $burnFee), // Burn Fee Amount
            TokenHelper::mulRc($numberOfTokens, $inviteFee), // Invite Fee Amount
        ];
    }

    /**
     * Make PEER to PEER Token Transfer.
     *
     * This function handles the transfer of tokens between users, applying necessary fees and ensuring all validations.
     * It also stores the transaction details.
     * Different fees are stored individually for transparency.
     *
     * @param string $numberOfTokens Number of tokens to transfer to Receipient's wallet, Without any Fees.
     *
     */
    public function transferToken(
        string $senderId,
        string $recipientId,
        string $numberOfTokens,
        TransferStrategy $strategy,
        ?string $message = null,
    ): ?array {
        \ignore_user_abort(true);

        $this->initializeLiquidityPool();

        if (!$this->validateFeesWalletUUIDs()) {
            return self::respondWithError(41222);
        }

        $this->senderId = $senderId;

        try {
            // Lock both users' balances to prevent race conditions
            if (!empty($this->inviterId)) {
                $this->lockBalances([$this->inviterId, $senderId, $recipientId]);
            } else {
                $this->lockBalances([$senderId, $recipientId]);
            }
            $mode = $strategy->getFeePolicyMode();
            [$requiredAmount, $netRecipientAmount, $peerFeeAmount, $burnFeeAmount, $inviteFeeAmount] =
                $this->calculateAmountsForMode($senderId, $numberOfTokens, $mode);

            $operationid = $strategy->getOperationId();
            $transRepo = new TransactionRepository($this->logger, $this->db);

            // 1. SENDER: Debit From Account
            if ($requiredAmount) {
                // To defend against atomoicity issues, we will debit first and then create transaction record. $this->walletMapper->saveWalletEntry($senderId, $requiredAmount, 'DEDUCT');
                $this->walletMapper->debitIfSufficient($senderId, $requiredAmount);

            }

            // 2. RECIPIENT: Credit To Account
            if ($netRecipientAmount) {
                $payload = [
                    'operationid' => $operationid,
                    'transactiontype' => $strategy->getRecipientTransactionType(),
                    'senderid' => $senderId,
                    'recipientid' => $recipientId,
                    'tokenamount' => $netRecipientAmount,
                    'message' => $message,
                    'transferaction' => 'CREDIT'
                ];
                $transactionId = $strategy->getTransactionId();
                if (!empty($transactionId)) {
                    $payload['transactionid'] = $transactionId;
                }
                $this->createAndSaveTransaction($transRepo, $payload);

                // To defend against atomicity issues, using credit method. If Not expected then use Default saveWalletEntry method. $this->walletMapper->saveWalletEntry($recipientId, $numberOfTokens);
                $this->walletMapper->credit($recipientId, $netRecipientAmount);
            }

            // 3. INVITER: Fees To Inviter (if applicable)
            if (!empty($this->inviterId) && isset($inviteFeeAmount) && $inviteFeeAmount > 0) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => $strategy->getInviterFeeTransactionType(),
                    'senderid' => $senderId,
                    'recipientid' => $this->inviterId,
                    'tokenamount' => $inviteFeeAmount,
                    'transferaction' => 'INVITER_FEE'
                ]);
                // To defend against atomicity issues, using credit method. If Not expected then use Default saveWalletEntry method. $this->walletMapper->saveWalletEntry($this->inviterId, $inviteFeeAmount);
                $this->walletMapper->credit($this->inviterId, $inviteFeeAmount);

            }


            // 5. PEERWALLET: Fee To Peer Wallet
            if (isset($peerFeeAmount) && $peerFeeAmount > 0) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => $strategy->getPeerFeeTransactionType(),
                    'senderid' => $senderId,
                    'recipientid' => $this->peerWallet,
                    'tokenamount' => $peerFeeAmount,
                    'transferaction' => 'PEER_FEE'
                ]);
                // To defend against atomicity issues, using credit method. If Not expected then use Default saveWalletEntry method. $this->walletMapper->saveWalletEntry($this->peerWallet, $peerFeeAmount);
                $this->walletMapper->credit($this->peerWallet, $peerFeeAmount);

            }

            // 6. BURNWALLET: Burn Tokens
            if (isset($burnFeeAmount) && $burnFeeAmount > 0) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $operationid,
                    'transactiontype' => $strategy->getBurnFeeTransactionType(),
                    'senderid' => $senderId,
                    'recipientid' => $this->burnWallet,
                    'tokenamount' => $burnFeeAmount,
                    'transferaction' => 'BURN_FEE'
                ]);
                // To defend against atomicity issues, using credit method. If Not expected then use Default saveWalletEntry method. $this->walletMapper->saveWalletEntry($this->burnWallet, $burnFeeAmount);
                $this->walletMapper->credit($this->burnWallet, $burnFeeAmount);
            }

            $this->logger->debug('Token transfer completed successfully');

            return [
                'status' => 'success',
                'ResponseCode' => "11212",
                'tokenSend' => $netRecipientAmount,
                'tokensSubstractedFromWallet' => $requiredAmount,
                'createdat' => date('Y-m-d H:i:s.u')
            ];
        } catch (\Throwable $e) {
            $this->logger->error('PeerTokenMapper.transferToken failed', [
                'error' => $e->getMessage(),
                'senderId' => $senderId,
                'recipientId' => $recipientId,
                'numberOfTokens' => $numberOfTokens,
                'trace' => $e->getTraceAsString(),
            ]);
            return self::respondWithError(40301);
        }
    }

    /**
     * Calculate required (debit) and net (recipient credit) amounts based on fee policy mode.
     * Returns array: [required, net, peerFeeAmount, poolFeeAmount, burnFeeAmount, inviteFeeAmount]
     */
    private function calculateAmountsForMode(string $senderId, string $inputAmount, FeePolicyMode $mode): array
    {
        $this->senderId = $senderId;
        [$peerFee, $burnFee, $inviteFee] = $this->getEachFeesAmount();

        // Sum of fee rates
        $sum1 = TokenHelper::addRc($peerFee, $burnFee);
        $totalRate = TokenHelper::addRc($sum1, $inviteFee);

        if ($mode === FeePolicyMode::ADDED) {
            $net = $inputAmount;
            $required = TokenHelper::calculateTokenRequiredAmount($net, $peerFee, $burnFee, $inviteFee);
            [$peerAmt, $burnAmt, $inviteAmt] = $this->calculateEachFeesAmount($net);
            return [$required, $net, $peerAmt, $burnAmt, $inviteAmt];
        }

        // INCLUDED mode: input is gross (required)
        $required = $inputAmount;
        $onePlus = TokenHelper::addRc('1', $totalRate);
        $net = TokenHelper::divRc($required, $onePlus);
        [$peerAmt, $poolAmt, $burnAmt, $inviteAmt] = $this->calculateEachFeesAmount($net);
        return [$required, $net, $peerAmt, $poolAmt, $burnAmt, $inviteAmt];
    }

    /**
     * Public helper to calculate only the required debit amount for a given mode.
     */
    public function calculateRequiredAmountByMode(string $senderId, string $inputAmount, FeePolicyMode $mode): string
    {
        if ($mode === FeePolicyMode::ADDED) {
            return $this->calculateRequiredAmount($senderId, $inputAmount);
        }
        // INCLUDED: gross equals required
        return $inputAmount;
    }

    /**
     * Get User balance
     *
     * @param $userId string
     * @param $hashedPassword string
     *
     * @return string value
     */
    public function getUserWalletBalance(string $userId): string
    {
        $this->logger->debug('WalletMapper.getUserWalletBalance started');

        $query = "SELECT liquidity AS balance 
                  FROM wallett 
                  WHERE userid = :userId FOR UPDATE";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->execute();
            $balance = $stmt->fetchColumn();

            $this->logger->debug('Fetched wallet balance', ['balance' => $balance]);

            return $balance;
        } catch (\PDOException $e) {
            $this->logger->error('Database error in getUserWalletBalance: ' . $e->getMessage());
            throw new \RuntimeException('Unable to fetch wallet balance');
        }
    }

    /**
     * Helper to create and save a transaction
     */
    private function createAndSaveTransaction($transRepo, array $transObj): void
    {
        $transaction = new Transaction($transObj, ['operationid', 'senderid', 'tokenamount'], false);
        $transRepo->saveTransaction($transaction);
    }

    /**
     *
     * get transcations history of current user.
     *
     */
    // DONE
    /**
     *
     * get transcations history of current user.
     *
     */
    public function getTransactions(string $userId, array $args): ?array
    {
        $this->logger->debug('PeerTokenMapper.getTransactions started');

        // Resolve filters from simple enums to actual DB values
        [$transactionTypes, $transferActions] = $this->resolveFilters($args);

        // Base select with sender/recipient user details
        $query =
            "SELECT tt.*,
                us.username AS sender_username, 
                us.uid AS sender_userid, 
                us.slug AS sender_slug,
                us.status AS sender_status, 
                us.img AS sender_img, 
                us.biography AS sender_biography, 
                us.visibility_status AS sender_visibility_status, 
                us.updatedat AS sender_updatedat,
                ur.username AS recipient_username, 
                ur.uid AS recipient_userid, 
                ur.slug AS recipient_slug,
                ur.status AS recipient_status, 
                ur.img AS recipient_img, 
                ur.biography AS recipient_biography, 
                ur.updatedat AS recipient_updatedat,
                ur.visibility_status AS recipient_visibility_status
                FROM transactions tt
                LEFT JOIN users AS us ON us.uid = tt.senderid
                LEFT JOIN users AS ur ON ur.uid = tt.recipientid
                WHERE (tt.senderid = :senderid OR tt.recipientid = :recipientid)";

        $params = [
            ':senderid' => $userId,
            ':recipientid' => $userId,
        ];

        // Apply type and action filters
        $query .= $this->appendInFilter('tt.transactiontype', $transactionTypes, $params, 'type');
        $query .= $this->appendInFilter('tt.transferaction', $transferActions, $params, 'action');

        // Date filters (YYYY-MM-DD)
        if (!empty($args['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['start_date'])) {
            $query .= ' AND tt.createdat >= :start_date';
            $params[':start_date'] = $args['start_date'] . ' 00:00:00';
        }
        if (!empty($args['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $args['end_date'])) {
            $query .= ' AND tt.createdat <= :end_date';
            $params[':end_date'] = $args['end_date'] . ' 23:59:59';
        }

        // Sort
        $sortDirection = 'DESC';
        if (!empty($args['sort'])) {
            $sortValue = strtoupper(trim((string) $args['sort']));
            $sortDirection = $sortValue === 'OLDEST' ? 'ASC' : ($sortValue === 'NEWEST' ? 'DESC' : 'DESC');
        }
        $query .= " ORDER BY tt.createdat $sortDirection";

        // Pagination
        if (isset($args['limit']) && is_numeric($args['limit'])) {
            $query .= ' LIMIT :limit';
            $params[':limit'] = (int) $args['limit'];
        }
        if (isset($args['offset']) && is_numeric($args['offset'])) {
            $query .= ' OFFSET :offset';
            $params[':offset'] = (int) $args['offset'];
        }

        try {
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $stmt->execute();

            $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $data = array_map(fn ($t) => $this->mapTransaction($t), $transactions);

            return [
                'status' => 'success',
                'ResponseCode' => '11215',
                'affectedRows' => $data,
            ];
        } catch (\Throwable $th) {
            $this->logger->error('Database error while fetching transactions - PeerTokenMapper.getTransactions', [
                'error' => $th->getMessage(),
            ]);
            throw new \RuntimeException('Database error while fetching transactions: ' . $th->getMessage());
        }
    }


    /**
     * Resolve filters for transaction queries.
     */
    private function resolveFilters(array $args): array
    {
        $typeMap = [
            'TRANSACTION' => ['transferSenderToRecipient', 'transferDeductSenderToRecipient'],
            'AIRDROP' => ['airdrop'],
            'MINT' => ['mint'],
            'FEES' => ['transferSenderToBurnWallet', 'transferSenderToPeerWallet', 'transferSenderToPoolWallet', 'transferSenderToInviter'],
        ];
        $directionMap = [
            'INCOME' => ['CREDIT'],
            'DEDUCTION' => ['DEDUCT', 'BURN_FEE', 'POOL_FEE', 'PEER_FEE', 'INVITER_FEE'],
        ];

        $transactionTypes = [];
        if (!empty($args['type'])) {
            $key = strtoupper((string) $args['type']);
            $transactionTypes = $typeMap[$key] ?? [];
        }

        $transferActions = [];
        if (!empty($args['direction'])) {
            $key = strtoupper((string) $args['direction']);
            $transferActions = $directionMap[$key] ?? [];
        }

        return [$transactionTypes, $transferActions];
    }

    /**
     * Append an IN filter to the query if values are provided.
     */
    private function appendInFilter(string $column, array $values, array &$params, string $prefix): string
    {
        if (empty($values)) {
            return '';
        }
        $phs = [];
        foreach ($values as $i => $val) {
            $ph = ":{$prefix}{$i}";
            $phs[] = $ph;
            $params[$ph] = $val;
        }
        return ' AND ' . $column . ' IN (' . implode(',', $phs) . ')';
    }

    /**
     * Map transaction with sender and recipient details.
     */
    private function mapTransaction(array $trans): array
    {
        $items = (new Transaction($trans, [], false))->getArrayCopy();
        $items['sender'] = (new User([
            'username' => $trans['sender_username'] ?? null,
            'uid' => $trans['sender_userid'] ?? null,
            'slug' => $trans['sender_slug'] ?? null,
            'status' => $trans['sender_status'] ?? null,
            'img' => $trans['sender_img'] ?? null,
            'biography' => $trans['sender_biography'] ?? null,
            'updatedat' => $trans['sender_updatedat'] ?? null,
            'visibility_status' => $trans['sender_visibility_status'],
        ], [], false))->getArrayCopy();

        $items['recipient'] = (new User([
            'username' => $trans['recipient_username'] ?? null,
            'uid' => $trans['recipient_userid'] ?? null,
            'slug' => $trans['recipient_slug'] ?? null,
            'status' => $trans['recipient_status'] ?? null,
            'img' => $trans['recipient_img'] ?? null,
            'biography' => $trans['recipient_biography'] ?? null,
            'visibility_status' => $trans['recipient_visibility_status'],
        ], [], false))->getArrayCopy();

        return $items;
    }


    /**
     * Lock balances of both users to prevent race conditions
     * Also Includes Fees wallets
     */
    private function lockBalances(array $userIds): void
    {
        $walletsToLock = [...$userIds];

        $fees = ConstantsConfig::tokenomics()['FEES_STRING'];
        if (isset($fees['PEER']) && (float)$fees['PEER'] > 0) {
            $walletsToLock[] = $this->peerWallet;
        }
        if (isset($fees['BURN']) && (float)$fees['BURN'] > 0) {
            $walletsToLock[] = $this->burnWallet;
        }
        if (isset($this->btcpool) && !empty($this->btcpool)) {
            $walletsToLock[] = $this->btcpool;
        }
        // Remove duplicates
        $walletsToLock = array_unique($walletsToLock);

        // Sort to ensure consistent locking order
        sort($walletsToLock);

        foreach ($walletsToLock as $walletId) {
            if (!self::isValidUUID($walletId)) {
                throw new \RuntimeException('Invalid wallet UUID for locking: ' . $walletId);
            }
            $this->lockWalletBalance($walletId);
        }
    }

    /**
     * Lock a single wallet balance for update.
     */
    private function lockWalletBalance(string $walletId): void
    {
        $query = "SELECT liquidity FROM wallett WHERE userid = :userid FOR UPDATE";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':userid', $walletId, \PDO::PARAM_STR);
        $stmt->execute();
        // Fetching the row to ensure the lock is acquired
        $stmt->fetch(\PDO::FETCH_ASSOC);
    }

}
