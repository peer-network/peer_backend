<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\App\Models\Transaction;
use Fawaz\App\Repositories\TransactionRepository;
use PDO;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use Fawaz\Utils\PeerLoggerInterface;
use RuntimeException;
use Fawaz\App\Status;
use Fawaz\config\constants\ConstantsConfig;

class PeerTokenMapper
{
    use ResponseHelper;
    private string $poolWallet;
    private string $burnWallet;
    private string $peerWallet;
    private string $btcpool;

    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db, protected LiquidityPool $pool, protected WalletMapper $walletMapper)
    {
    }

    /**
     * Loads and validates the liquidity pool wallets.
     *
     * @throws \RuntimeException if accounts are missing or invalid
     */
    private function initializeLiquidityPool(): void
    {
        $accounts = $this->pool->returnAccounts();
        if (($accounts['status'] ?? '') === 'error') {
            throw new \RuntimeException("Failed to load pool accounts");
        }

        $data = $accounts['response'] ?? [];
        if (!isset($data['pool'], $data['burn'], $data['peer'])) {
            throw new \RuntimeException("Liquidity pool wallets incomplete");
        }

        $this->poolWallet = $data['pool'];
        $this->burnWallet = $data['burn'];
        $this->peerWallet = $data['peer'];
        $this->btcpool = $data['btcpool'];
    }

    /**
     * Validate fees wallet UUID.
     *
     * @param $inputPassword string
     * @param $hashedPassword string
     *
     * @return bool value
     */
    private function validateFeesWalletUUIDs(): bool
    {
        return self::isValidUUID($this->poolWallet)
            && self::isValidUUID($this->burnWallet)
            && self::isValidUUID($this->peerWallet)
            && self::isValidUUID($this->btcpool);
    }

    /**
     * Make peer token transfer to recipient.
     *
     */
    public function transferToken(string $userId, array $args = []): ?array
    {
        \ignore_user_abort(true);

        $this->logger->debug('PeerTokenMapper.transferToken started');

        $recipient = (string) $args['recipient'];

        if ((string) $recipient === $userId) {
            $this->logger->warning('Send and Receive Same Wallet Error.');
            return self::respondWithError(31202);
        }

        if (!self::isValidUUID($recipient)) {
            $this->logger->warning('Incorrect recipientid Exception.', [
                'recipient' => $recipient,
            ]);
            return self::respondWithError(30201);
        }

        $this->initializeLiquidityPool();

        if (!$this->validateFeesWalletUUIDs()) {
            return self::respondWithError(41222);
        }

        try {
            $sql = "SELECT uid FROM users WHERE uid = :uid AND status != :status";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':uid', $recipient);
            $stmt->bindValue(':status', Status::DELETED);
            $stmt->execute();
            $row = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->logger->error('Database error while fetching recipient user', [
                'error' => $e->getMessage(),
                'recipient' => $recipient
            ]);
            return self::respondWithError(31007);
        }
        $inviterId = $this->getInviterID($userId);

        // Lock both users' balances to prevent race conditions
        if ($inviterId && !empty($inviterId)) {
            $this->lockBalances([$inviterId, $userId, $recipient]);
        }else{
            $this->lockBalances([$userId, $recipient]);
        }
        $currentBalance = $this->getUserWalletBalance($userId);

        if (empty($currentBalance)) {
            $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(51301);
        }

        if ($this->poolWallet == $recipient || $this->burnWallet == $recipient || $this->peerWallet == $recipient || $this->btcpool == $recipient) {
            $this->logger->warning('Unauthorized to send token');
            return self::respondWithError(31203);
        }

        if (!isset($args['numberoftokens']) || !is_numeric($args['numberoftokens']) || (float) $args['numberoftokens'] != $args['numberoftokens']) {
            return self::respondWithError(30264);
        }

        $numberoftokens = (float) $args['numberoftokens'];
        if ($numberoftokens <= 0) {
            $this->logger->warning('Incorrect Amount Exception: ZERO or less than token should not be transfer', [
                'numberoftokens' => $numberoftokens,
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(30264);
        }
        $message = isset($args['message']) ? (string) $args['message'] : null;


        if ($message !== null && strlen($message) > 200) {
            $this->logger->warning('message length is too high');
            return self::respondWithError(30210); // message length is too high.
        }

        if (empty($row)) {
            $this->logger->warning('Unknown Id Exception.');
            return self::respondWithError(31007);
        }

        if ((string)$row === $userId) {
            $this->logger->warning('Send and Receive Same Wallet Error.');
            return self::respondWithError(31202);
        }

        $fees = ConstantsConfig::tokenomics()['FEES'];
        $actions = ConstantsConfig::wallet()['ACTIONS'];
        $peerFee = (float) $fees['PEER'];
        $poolFee = (float) $fees['POOL'];
        $burnFee = (float) $fees['BURN'];
        $inviteFee = (float)$fees['INVITATION'];
        $requiredAmount = TokenHelper::calculateTokenRequiredAmount($numberoftokens, $peerFee, $poolFee, $burnFee);

        try {
            if ($inviterId && !empty($inviterId)) {
                $inviterWin = TokenHelper::mulRc($numberoftokens, $inviteFee);

                $requiredAmount = TokenHelper::calculateTokenRequiredAmount($numberoftokens, $peerFee, $poolFee, $burnFee, $inviteFee);

                $this->logger->info('Invited By', [
                    'invited' => $inviterId,
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Error while fetching inviter ID', [
                'error' => $e->getMessage(),
                'userId' => $userId
            ]);
            return self::respondWithError(31007);
        }

        if ($currentBalance < $requiredAmount) {
            $this->logger->warning('No Coverage Exception: Not enough balance to perform this action.', [
                'userId' => $userId,
                'Balance' => $currentBalance,
                'requiredAmount' => $requiredAmount,
            ]);
            return self::respondWithError(51301);
        }

        try {

            $transUniqueId = self::generateUUID();
            $transRepo = new TransactionRepository($this->logger, $this->db);


            // 1. SENDER: Debit From Account
            if ($requiredAmount) {
                // Remove this records we don't need it anymore.
                // $this->createAndSaveTransaction($transRepo, [
                //     'operationid' => $transUniqueId,
                //     'transactiontype' => 'transferDeductSenderToRecipient',
                //     'senderid' => $userId,
                //     'tokenamount' => -$requiredAmount,
                //     'message' => $message
                // ]);

                $id = self::generateUUID();

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => -abs($requiredAmount),
                    'whereby' => $actions['TRANSFER'],
                ];

                $this->walletMapper->insertWinToLog($userId, $args);
                $this->walletMapper->insertWinToPool($userId, $args);

            }

            // 2. RECIPIENT: Credit To Account
            if ($numberoftokens) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $transUniqueId,
                    'transactiontype' => 'transferSenderToRecipient',
                    'senderid' => $userId,
                    'recipientid' => $recipient,
                    'tokenamount' => $numberoftokens,
                    'message' => $message,
                    'transferaction' => 'CREDIT'
                ]);

                $id = self::generateUUID();

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($numberoftokens),
                    'whereby' => $actions['TRANSFER'],
                ];

                $this->walletMapper->insertWinToLog($recipient, $args);
                $this->walletMapper->insertWinToPool($recipient, $args);
            }

            // 3. INVITER: Fees To Inviter (if applicable)
            if (!empty($inviterId) && $inviterWin) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $transUniqueId,
                    'transactiontype' => 'transferSenderToInviter',
                    'senderid' => $userId,
                    'recipientid' => $inviterId,
                    'tokenamount' => $inviterWin,
                    'transferaction' => 'INVITER_FEE'
                ]);
                $id = self::generateUUID();

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($inviterWin),
                    'whereby' => $actions['TRANSFER'],
                ];

                $this->walletMapper->insertWinToLog($inviterId, $args);
                $this->walletMapper->insertWinToPool($inviterId, $args);
            }

            // 4. POOLWALLET: Fee To Pool Wallet
            $feeAmount = TokenHelper::mulRc($numberoftokens, $poolFee);
            if ($feeAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $transUniqueId,
                    'transactiontype' => 'transferSenderToPoolWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->poolWallet,
                    'tokenamount' => $feeAmount,
                    'transferaction' => 'POOL_FEE'
                ]);
                $id = self::generateUUID();

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($feeAmount),
                    'whereby' => $actions['TRANSFER'],
                ];

                $this->walletMapper->insertWinToLog($this->poolWallet, $args);
                $this->walletMapper->insertWinToPool($this->poolWallet, $args);
            }

            // 5. PEERWALLET: Fee To Peer Wallet
            $peerAmount = TokenHelper::mulRc($numberoftokens, $peerFee);
            if ($peerAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $transUniqueId,
                    'transactiontype' => 'transferSenderToPeerWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->peerWallet,
                    'tokenamount' => $peerAmount,
                    'transferaction' => 'PEER_FEE'
                ]);
                $id = self::generateUUID();

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($peerAmount),
                    'whereby' => $actions['TRANSFER'],
                ];

                $this->walletMapper->insertWinToLog($this->peerWallet, $args);
                $this->walletMapper->insertWinToPool($this->peerWallet, $args);
            }

            // 6. BURNWALLET: Burn Tokens
            $burnAmount = TokenHelper::mulRc($numberoftokens, $burnFee);
            if ($burnAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $transUniqueId,
                    'transactiontype' => 'transferSenderToBurnWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->burnWallet,
                    'tokenamount' => $burnAmount,
                    'transferaction' => 'BURN_FEE'
                ]);
                $id = self::generateUUID();

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($burnAmount),
                    'whereby' => $actions['TRANSFER'],
                ];
                $this->walletMapper->insertWinToLog($this->burnWallet, $args);
                $this->walletMapper->insertWinToPool($this->burnWallet, $args);
            }

            $this->logger->info('Token transfer completed successfully');

            return [
                'status' => 'success',
                'ResponseCode' => "11212",
                'tokenSend' => $numberoftokens,
                'tokensSubstractedFromWallet' => $requiredAmount,
                'createdat' => date('Y-m-d H:i:s.u')
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Error during token transfer', [
                'error' => $e->getMessage(),
                'userId' => $userId,
                'recipient' => $recipient,
                'numberoftokens' => $numberoftokens
            ]);
            return self::respondWithError(40301);
        }
    }

    private function getInviterID(string $userId): ?string
    {
        try {
            $query = "SELECT invited FROM users_info WHERE userid = :userid AND invited IS NOT NULL";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['userid' => $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (isset($result['invited']) && !empty($result['invited'])) {
                return $result["invited"];
            }
            return null;
        } catch (\Throwable $e) {
            throw new RuntimeException($e->getMessage());
        }
    }


    /**
     * get Liquidity in Q96.
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

            $this->logger->info('Fetched wallet balance', ['balance' => $balance]);

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
    public function getTransactions(string $userId, array $args): ?array
    {
        $this->logger->debug("PeerTokenMapper.getTransactions started");

        // Define FILTER mappings.
        $typeMap = [
            'TRANSACTION' => ['transferSenderToRecipient', 'transferDeductSenderToRecipient'],
            'AIRDROP' => ['airdrop'],
            'MINT' => ['mint'],
            'FEES' => ['transferSenderToBurnWallet', 'transferSenderToPeerWallet', 'transferSenderToPoolWallet', 'transferSenderToInviter']
        ];

        // Define DIRECTION FILTER mappings.
        $directionMap = [
            'INCOME' => ['CREDIT'],
            'DEDUCTION' => ['DEDUCT', 'BURN_FEE', 'POOL_FEE', 'PEER_FEE', 'INVITER_FEE']
        ];

        $transactionTypes = isset($args['type']) ? ($typeMap[$args['type']] ?? []) : [];
        $transferActions = isset($args['direction']) ? ($directionMap[$args['direction']] ?? []) : [];

        $query = "SELECT * FROM transactions WHERE (senderid = :senderid OR recipientid = :recipientid)";

        $params = [':senderid' => $userId, ':recipientid' => $userId];

        // Handle TRANSACTION TYPE filter.
        if (!empty($transactionTypes)) {
            $typePlaceholders = [];
            foreach ($transactionTypes as $i => $type) {
                $ph = ":type$i";
                $typePlaceholders[] = $ph;
                $params[$ph] = $type;
            }
            $query .= " AND transactiontype IN (" . implode(',', $typePlaceholders) . ")";
        }

        // Handle TRANSFER ACTION filter.
        if (!empty($transferActions)) {
            $actionPlaceholders = [];
            foreach ($transferActions as $i => $action) {
                $ph = ":action$i";
                $actionPlaceholders[] = $ph;
                $params[$ph] = $action;
            }
            $query .= " AND transferaction IN (" . implode(',', $actionPlaceholders) . ")";
        }

        // Handle DATE filters.(accepting only date, appending time internally)
        if (isset($args['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $args['start_date'])) {
            $query .= " AND createdat >= :start_date";
            $params[':start_date'] = $args['start_date'] . ' 00:00:00';
        }

        if (isset($args['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $args['end_date'])) {
            $query .= " AND createdat <= :end_date";
            $params[':end_date'] = $args['end_date'] . ' 23:59:59';
        }

        // Handle SORT safely.(accept ASCENDING or DESCENDING)
        $sortDirection = 'DESC'; // default
        if (isset($args['sort'])) {
            $sortValue = strtoupper(trim($args['sort']));
            if ($sortValue === 'OLDEST') {
                $sortDirection = 'ASC';
            } elseif ($sortValue === 'NEWEST') {
                $sortDirection = 'DESC';
            }
        }
        $query .= " ORDER BY createdat $sortDirection";

        // Handle PAGINATION.(limit and offset)
        if (isset($args['limit']) && is_numeric($args['limit'])) {
            $query .= " LIMIT :limit";
            $params[':limit'] = (int) $args['limit'];
        }

        if (isset($args['offset']) && is_numeric($args['offset'])) {
            $query .= " OFFSET :offset";
            $params[':offset'] = (int) $args['offset'];
        }

        try {
            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }

            $stmt->execute();
            $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $data = array_map(
                fn ($trans) => (new Transaction($trans, [], false))->getArrayCopy(),
                $transactions
            );

            return [
                'status' => 'success',
                'ResponseCode' => "11215",
                'affectedRows' => $data
            ];
        } catch (\Throwable $th) {
            $this->logger->error("Database error while fetching transactions - PeerTokenMapper.getTransactions", [
                'error' => $th->getMessage()
            ]);
            throw new \RuntimeException("Database error while fetching transactions: " . $th->getMessage());
        }
    }
    /**
     * Lock balances of both users to prevent race conditions
     * Also Includes Fees wallets
     */
    private function lockBalances(array $userIds): void
    {
        $walletsToLock = [...$userIds];

        $fees = ConstantsConfig::tokenomics()['FEES'];
        if (isset($fees['PEER']) && (float)$fees['PEER'] > 0) {
            $walletsToLock[] = $this->peerWallet;
        }
        if (isset($fees['POOL']) && (float)$fees['POOL'] > 0) {
            $walletsToLock[] = $this->poolWallet;
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
                $this->logger->debug('Invalid wallet UUID for locking', ['walletId' => $walletId]);
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
        $this->logger->debug('Locking wallet balance', ['walletId' => $walletId]);
        $query = "SELECT liquidity FROM wallett WHERE userid = :userid FOR UPDATE";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':userid', $walletId, \PDO::PARAM_STR);
        $stmt->execute();
        // Fetching the row to ensure the lock is acquired
        $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->logger->debug('Wallet balance locked', ['walletId' => $walletId]);
    }

}