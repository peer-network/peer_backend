<?php

namespace Fawaz\Database;

use Fawaz\App\Models\BtcSwapTransaction;
use Fawaz\App\Models\Transaction;
use Fawaz\App\Repositories\BtcSwapTransactionRepository;
use Fawaz\App\Repositories\TransactionRepository;
use PDO;
use Fawaz\Services\BtcService;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use Fawaz\Utils\TokenCalculations\SwapTokenHelper;
use Psr\Log\LoggerInterface;
use RuntimeException;


class PeerTokenMapper
{
    use ResponseHelper;
    private string $poolWallet;
    private string $burnWallet;
    private string $peerWallet;
    private string $btcpool;

    public function __construct(protected LoggerInterface $logger, protected PDO $db, protected LiquidityPool $pool, protected WalletMapper $walletMapper) {}

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
     * @param userId string
     * @param args array
     * 
     */
    public function transferToken(string $userId, array $args = []): ?array
    {
        \ignore_user_abort(true);

        $this->logger->info('PeerTokenMapper.transferToken started');

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

        $currentBalance = $this->getUserWalletBalance($userId);

        if (empty($currentBalance)) {
            $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(51301);
        }

        if ($this->poolWallet == $recipient || $this->burnWallet == $recipient || $this->peerWallet == $recipient || $this->btcpool == $recipient) {
            $this->logger->warning('Unauthorized to send token');
            return self::respondWithError(31203); // Unauthorized to send token.
        }

        if (!isset($args['numberoftokens']) || !is_numeric($args['numberoftokens']) || (float) $args['numberoftokens'] != $args['numberoftokens']) {
            return self::respondWithError(30264); // Invalid token amount provided. It is should be Integer or with decimal numbers
        }

        $numberoftokens = (string) $args['numberoftokens'];
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

        try {
            $sql = "SELECT uid FROM users WHERE uid = :uid";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':uid', $recipient);
            $stmt->execute();
            $row = $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return self::respondWithError($e->getMessage());
        }

        if (empty($row)) {
            $this->logger->warning('Unknown Id Exception.');
            return self::respondWithError(31003);
        }

        if ((string)$row === $userId) {
            $this->logger->warning('Send and Receive Same Wallet Error.');
            return self::respondWithError(31202);
        }

        $requiredAmount = TokenHelper::calculateTokenRequiredAmount($numberoftokens, PEERFEE, POOLFEE, BURNFEE);

        $inviterId = $this->getInviterID($userId);
        try {
            if ($inviterId && !empty($inviterId)) {
                $inviterWin = TokenHelper::mulRc($numberoftokens, INVTFEE);

                $requiredAmount = TokenHelper::calculateTokenRequiredAmount($numberoftokens, PEERFEE, POOLFEE, BURNFEE, INVTFEE);

                $this->logger->info('Invited By', [
                    'invited' => $inviterId,
                ]);
            }
        } catch (\Throwable $e) {
            return self::respondWithError($e->getMessage());
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
                $this->createAndSaveTransaction($transRepo, [
                    'transuniqueid' => $transUniqueId,
                    'transactiontype' => 'transferDeductSenderToRecipient',
                    'senderid' => $userId,
                    'tokenamount' => -$requiredAmount,
                    'message' => $message
                ]);
                $this->saveWalletEntry($userId, $requiredAmount, 'DEBIT');
            }

            // 2. RECIPIENT: Credit To Account
            if ($numberoftokens) {
                $this->createAndSaveTransaction($transRepo, [
                    'transuniqueid' => $transUniqueId,
                    'transactiontype' => 'transferSenderToRecipient',
                    'senderid' => $userId,
                    'recipientid' => $recipient,
                    'tokenamount' => $numberoftokens,
                    'message' => $message,
                    'transferaction' => 'CREDIT'
                ]);
                $this->saveWalletEntry($row, $numberoftokens);
            }

            // 3. INVITER: Fees To Inviter (if applicable)
            if (!empty($inviterId) && $inviterWin) {
                $this->createAndSaveTransaction($transRepo, [
                    'transuniqueid' => $transUniqueId,
                    'transactiontype' => 'transferSenderToInviter',
                    'senderid' => $userId,
                    'recipientid' => $inviterId,
                    'tokenamount' => $inviterWin,
                    'transferaction' => 'INVITER_FEE'
                ]);
                $this->saveWalletEntry($inviterId, $inviterWin);
            }

            // 4. POOLWALLET: Fee To Pool Wallet
            $feeAmount = TokenHelper::mulRc($numberoftokens, POOLFEE);
            if ($feeAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'transuniqueid' => $transUniqueId,
                    'transactiontype' => 'transferSenderToPoolWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->poolWallet,
                    'tokenamount' => $feeAmount,
                    'transferaction' => 'POOL_FEE'
                ]);
                $this->saveWalletEntry($this->poolWallet, $feeAmount);
            }

            // 5. PEERWALLET: Fee To Peer Wallet
            $peerAmount = TokenHelper::mulRc($numberoftokens, PEERFEE);
            if ($peerAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'transuniqueid' => $transUniqueId,
                    'transactiontype' => 'transferSenderToPeerWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->peerWallet,
                    'tokenamount' => $peerAmount,
                    'transferaction' => 'PEER_FEE'
                ]);
                $this->saveWalletEntry($this->peerWallet, $peerAmount);
            }

            // 6. BURNWALLET: Burn Tokens
            $burnAmount = TokenHelper::mulRc($numberoftokens, BURNFEE);
            if ($burnAmount) {
                $this->createAndSaveTransaction($transRepo, [
                    'transuniqueid' => $transUniqueId,
                    'transactiontype' => 'transferSenderToBurnWallet',
                    'senderid' => $userId,
                    'recipientid' => $this->burnWallet,
                    'tokenamount' => $burnAmount,
                    'transferaction' => 'BURN_FEE'
                ]);
                $this->saveWalletEntry($this->burnWallet, $burnAmount);
            }

            return [
                'status' => 'success',
                'ResponseCode' => 11212,
                'tokenSend' => $numberoftokens,
                'tokensSubstractedFromWallet' => $requiredAmount,
                'createdat' => date('Y-m-d H:i:s.u')
            ];
        } catch (\Throwable $e) {
            return self::respondWithError($e->getMessage());
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
            return NULL;
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
     * @return bool value
     */
    public function getUserWalletBalance(string $userId): string
    {
        $this->logger->info('WalletMapper.getUserWalletBalance started');

        $query = "SELECT liquidity AS balance 
                  FROM wallett 
                  WHERE userid = :userId";

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
        $transaction = new Transaction($transObj, ['transuniqueid', 'senderid', 'tokenamount'], false);
        $transRepo->saveTransaction($transaction);
    }


    /**
     * Save wallet entry.
     *
     * @param $inputPassword string
     * @param $hashedPassword string
     * 
     * @return bool value
     */
    public function saveWalletEntry(string $userId, string $liquidity, $direction = 'CREDIT'): float
    {
        \ignore_user_abort(true);
        $this->logger->info('PeerTokenMapper.saveWalletEntry started');

        try {
            $this->db->beginTransaction();

            $query = "SELECT 1 FROM wallett WHERE userid = :userid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
            $stmt->execute();
            $userExists = $stmt->fetchColumn();

            if ($userExists) {
                // Q96
                $currentBalance = $this->getUserWalletBalance($userId);

                if ($direction == 'CREDIT') {
                    $newLiquidity = TokenHelper::addRc($currentBalance, $liquidity);
                } elseif ('DEBIT') {
                    $newLiquidity = TokenHelper::subRc($currentBalance, $liquidity);
                } else {
                    throw new \RuntimeException('Unknown Action while save Wallet entry');
                }

                $liquiditq = TokenHelper::convertToQ96($newLiquidity);

                $query = "UPDATE wallett
                          SET liquidity = :liquidity, liquiditq = :liquiditq, updatedat = :updatedat
                          WHERE userid = :userid";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                $stmt->bindValue(':liquidity', $newLiquidity, \PDO::PARAM_STR);
                $stmt->bindValue(':liquiditq', $liquiditq, \PDO::PARAM_STR);
                $stmt->bindValue(':updatedat', (new \DateTime())->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);

                $stmt->execute();
            }

            $this->db->commit();
            $this->logger->info('Wallet entry saved successfully', ['newLiquidity' => $newLiquidity]);
            $this->walletMapper->updateUserLiquidity($userId, $newLiquidity);

            return $newLiquidity;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error('Database error in saveWalletEntry: ' . $e->getMessage());
            throw new \RuntimeException('Unable to save wallet entry');
        }
    }



    /**
     * 
     * get transcations history of current user.
     * 
     */
    // DONE
    public function getTransactions(string $userId, array $args): ?array
    {
        $this->logger->info("PeerTokenMapper.getTransactions started");

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

        $query = "SELECT * FROM transactions WHERE (senderid = :userid OR recipientid = :userid)";

        $params = [':userid' => $userId];

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

            return [
                'status' => 'success',
                'ResponseCode' => 11215,
                'affectedRows' => $transactions
            ];
        } catch (\Throwable $th) {
            $this->logger->error("Database error while fetching transactions - PeerTokenMapper.getTransactions", [
                'error' => $th->getMessage()
            ]);
            throw new \RuntimeException("Database error while fetching transactions: " . $th->getMessage());
        }
    }

}