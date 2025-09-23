<?php

namespace Fawaz\Database;

use DateTime;
use Fawaz\App\Models\Transaction;
use Fawaz\App\Repositories\TransactionRepository;
use Fawaz\Database\Interfaces\TransactionManager;
use PDO;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use Psr\Log\LoggerInterface;


class LogWinMapper
{
    use ResponseHelper;

    private string $burnWallet;
    private string $companyWallet;

    public function __construct(protected LoggerInterface $logger, protected PDO $db, protected LiquidityPool $pool, protected TransactionManager $transactionManager) {}

    /**
     * Initialize and validates the liquidity pool wallets.
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

        $this->burnWallet = $data['burn'];
        $this->companyWallet = $data['peer'];
    }

    /**
     * Migrate logwins records to transactions table.
     *
     * This Function only for Paid Actions such Post Creation, Like, Dislike, Views, Comment
     * 
     * Used for Actions records:
     * whereby = 1  -> Post Views
     * whereby = 2  -> Post Like
     * whereby = 3  -> Post Dislike
     * whereby = 4  -> Post Comment
     * whereby = 5  -> Post Creation
     * 
     * Table `Transactions` has Foreign key on `operationsid`, which refers to `logWins`'s `token` PK. 
     */
    public function migrateViewPaidActions(): bool
    {
        \ignore_user_abort(true);
        ini_set('max_execution_time', '0');
        set_time_limit(0);

        $this->logger->info('LogWinMapper.migrateLogwinData started');

        try {
            // Fetch up to 1000 un-migrated rows
            $sql = "SELECT * FROM logwins WHERE migrated = :migrated AND whereby = 1 LIMIT 1000;";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':migrated', 0, \PDO::PARAM_INT);
            $stmt->execute();

            $logwins = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (empty($logwins)) {
                $this->logger->info('No logwins to migrate');
                return true;
            }

            // Initialize heavy dependencies once
            $this->initializeLiquidityPool();
            $transRepo = new TransactionRepository($this->logger, $this->db);

            foreach ($logwins as $value) {
                // Begin per-row transaction (keep same semantics as before)
                $this->transactionManager->beginTransaction();

                try {
                    $userId = $value['userid'] ?? null;
                    $postId = $value['postid'] ?? null;
                    $numBers = isset($value['numbers']) ? (float)$value['numbers'] : 0.0;
                    $token = $value['token'] ?? null;

                    $this->logger->info('Migrating logwin', [
                        'userId' => $userId,
                        'postId' => $postId,
                        'token' => $token
                    ]);

                    // map whereby to transactionType
                    $transactionType = match ((int)($value['whereby'] ?? 0)) {
                        1 => 'postViewed',
                        2 => 'postLiked',
                        3 => 'postDisLiked',
                        4 => 'postComment',
                        5 => 'postCreated',
                        default => '',
                    };

                    // Determine transfer type and sender/recipient semantics
                    $senderid = $userId;
                    if ($numBers < 0) {
                        $transferType = 'BURN';
                        $recipientid = $this->burnWallet;
                    } else {
                        $transferType = 'MINT';
                        $recipientid = $userId;
                        // Company wallet is the sender for MINT operations (as your comment suggested)
                        $senderid = $this->companyWallet;
                    }

                    // create and save transaction (re-using the earlier prepared $transRepo)
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $token,
                        'transactiontype' => $transactionType,
                        'senderid' => $senderid,
                        'recipientid' => $recipientid,
                        'tokenamount' => $numBers,
                        'transferaction' => $transferType,
                        'createdat' => $value['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u')
                    ]);

                    if ($transferType === 'BURN') {
                        // Credit burn account (we stored negative numbers earlier)
                        $this->saveWalletEntry($this->burnWallet, -$numBers);
                    }


                    $this->transactionManager->commit();
                } catch (\Throwable $e) {
                    $this->transactionManager->rollback();

                    // mark as error (2) so it can be retried/inspected later
                    try {
                        if (!empty($value['token'])) {
                            $this->updateLogwinStatus($value['token'], 2); // Unmigrated due to some errors
                        }
                    } catch (\Throwable $inner) {
                        // Log but continue; don't let updateLogwinStatus failure mask original error
                        $this->logger->error('Failed to update logwin status after rollback', [
                            'token' => $value['token'] ?? null,
                            'exception' => $inner->getMessage()
                        ]);
                    }

                    $this->logger->error('Failed to migrate single logwin', [
                        'token' => $value['token'] ?? null,
                        'exception' => $e->getMessage()
                    ]);

                    // continue with next row
                    continue;
                }
            }

            // Bulk mark remaining tokens as migrated = 1 (defensive: might include rows migrated above)
            try {
                $tokens = array_column($logwins, 'token');
                // remove any null/empty tokens
                $tokens = array_values(array_filter($tokens, fn($t) => !empty($t)));
                if (!empty($tokens)) {
                    // Quote tokens safely
                    $quotedTokenIds = array_map(fn($tokenId) => $this->db->quote($tokenId), $tokens);
                    $this->db->query('UPDATE logwins SET migrated = 1 WHERE migrated = 0 AND token IN (' . \implode(',', $quotedTokenIds) . ')');
                }
            } catch (\Throwable $e) {
                $this->logger->error('Error updating logwins migrated flag (bulk update)', ['exception' => $e->getMessage()]);
            }

            $this->logger->info('Finished migrating logwins batch', [
                'count' => count($logwins)
            ]);

            return false; // Indicate there may be more to process
        } catch (\Throwable $e) {
            // Log and return false to indicate the migration run had a fatal issue
            $this->logger->error('migratePaidActions failed', ['exception' => $e->getMessage()]);
            return false;
        }
    }


    /**
     * Migrate logwins records to transactions table.
     *
     * This Function only for Paid Actions such Post Creation, Like, Dislike, Views, Comment
     * 
     * Used for Actions records:
     * whereby = 1  -> Post Views
     * whereby = 2  -> Post Like
     * whereby = 3  -> Post Dislike
     * whereby = 4  -> Post Comment
     * whereby = 5  -> Post Creation
     * 
     * Table `Transactions` has Foreign key on `operationsid`, which refers to `logWins`'s `token` PK. 
     */
    public function migrateOtherPaidActions(): bool
    {
        \ignore_user_abort(true);
        ini_set('max_execution_time', '0');
        set_time_limit(0);

        $this->logger->info('LogWinMapper.migrateLogwinData started');

        try {
            // Fetch up to 1000 un-migrated rows
            $sql = "SELECT * FROM logwins WHERE migrated = :migrated AND whereby IN(2, 3, 4, 5) LIMIT 1000;";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':migrated', 0, \PDO::PARAM_INT);
            $stmt->execute();

            $logwins = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if (empty($logwins)) {
                $this->logger->info('No logwins to migrate');
                return true;
            }

            // Initialize heavy dependencies once
            $this->initializeLiquidityPool();
            $transRepo = new TransactionRepository($this->logger, $this->db);

            foreach ($logwins as $value) {
                // Begin per-row transaction (keep same semantics as before)
                $this->transactionManager->beginTransaction();

                try {
                    $userId = $value['userid'] ?? null;
                    $postId = $value['postid'] ?? null;
                    $numBers = isset($value['numbers']) ? (float)$value['numbers'] : 0.0;
                    $token = $value['token'] ?? null;

                    $this->logger->info('Migrating logwin', [
                        'userId' => $userId,
                        'postId' => $postId,
                        'token' => $token
                    ]);

                    // map whereby to transactionType
                    $transactionType = match ((int)($value['whereby'] ?? 0)) {
                        1 => 'postViewed',
                        2 => 'postLiked',
                        3 => 'postDisLiked',
                        4 => 'postComment',
                        5 => 'postCreated',
                        default => '',
                    };

                    // Determine transfer type and sender/recipient semantics
                    $senderid = $userId;
                    if ($numBers < 0) {
                        $transferType = 'BURN';
                        $recipientid = $this->burnWallet;
                    } else {
                        $transferType = 'MINT';
                        $recipientid = $userId;
                        // Company wallet is the sender for MINT operations (as your comment suggested)
                        $senderid = $this->companyWallet;
                    }

                    // create and save transaction (re-using the earlier prepared $transRepo)
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $token,
                        'transactiontype' => $transactionType,
                        'senderid' => $senderid,
                        'recipientid' => $recipientid,
                        'tokenamount' => $numBers,
                        'transferaction' => $transferType,
                        'createdat' => $value['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u')
                    ]);

                    if ($transferType === 'BURN') {
                        // Credit burn account (we stored negative numbers earlier)
                        $this->saveWalletEntry($this->burnWallet, -$numBers);
                    }


                    $this->transactionManager->commit();
                } catch (\Throwable $e) {
                    $this->transactionManager->rollback();

                    // mark as error (2) so it can be retried/inspected later
                    try {
                        if (!empty($value['token'])) {
                            $this->updateLogwinStatus($value['token'], 2); // Unmigrated due to some errors
                        }
                    } catch (\Throwable $inner) {
                        // Log but continue; don't let updateLogwinStatus failure mask original error
                        $this->logger->error('Failed to update logwin status after rollback', [
                            'token' => $value['token'] ?? null,
                            'exception' => $inner->getMessage()
                        ]);
                    }

                    $this->logger->error('Failed to migrate single logwin', [
                        'token' => $value['token'] ?? null,
                        'exception' => $e->getMessage()
                    ]);

                    // continue with next row
                    continue;
                }
            }

            // Bulk mark remaining tokens as migrated = 1 (defensive: might include rows migrated above)
            try {
                $tokens = array_column($logwins, 'token');
                // remove any null/empty tokens
                $tokens = array_values(array_filter($tokens, fn($t) => !empty($t)));
                if (!empty($tokens)) {
                    // Quote tokens safely
                    $quotedTokenIds = array_map(fn($tokenId) => $this->db->quote($tokenId), $tokens);
                    $this->db->query('UPDATE logwins SET migrated = 1 WHERE migrated = 0 AND token IN (' . \implode(',', $quotedTokenIds) . ')');
                }
            } catch (\Throwable $e) {
                $this->logger->error('Error updating logwins migrated flag (bulk update)', ['exception' => $e->getMessage()]);
            }

            $this->logger->info('Finished migrating logwins batch', [
                'count' => count($logwins)
            ]);

            return false; // Indicate there may be more to process
        } catch (\Throwable $e) {
            // Log and return false to indicate the migration run had a fatal issue
            $this->logger->error('migratePaidActions failed', ['exception' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Records migration for Token Transfers
     * 
     * Used for Peer Token transfer to Receipient (Peer-to-Peer transfer).
     * 
     * Used for Actions records:
     * whereby = 18 -> Token transfer @deprecated
     * 
     */
    public function migrateTokenTransfer(): bool
    {
        \ignore_user_abort(true);

        ini_set('max_execution_time', '0');

        $this->logger->info('LogWinMapper.migrateTokenTransfer started');

        try {

            // Group by fromid to avoid duplicates
            $sql = "SELECT 
                        date_trunc('second', lw.createdat) AS created_at_second,
                        lw.fromid,
                        json_agg(lw ORDER BY lw.createdat) AS logwin_entries,
                        COUNT(*) AS logwin_count
                    FROM logwins lw
                    WHERE lw.migrated = 0
                    AND lw.whereby = 18
                    GROUP BY created_at_second, lw.fromid
                    HAVING COUNT(*) IN (6, 7)
                    ORDER BY created_at_second ASC, lw.fromid
                    LIMIT 100;
                ";


            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $logwins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($logwins)) {
                $this->logger->info('No logwins to migrate');
                return true;
            }

            $this->initializeLiquidityPool();
            $transRepo = new TransactionRepository($this->logger, $this->db);


            foreach ($logwins as $key => $value) {
                $this->transactionManager->beginTransaction();

                $tnxs = json_decode($value['logwin_entries'], true);

                $txnIds = array_column($tnxs, 'token');

                if (empty($tnxs)) {
                    continue;
                }

                $hasInviter = false;
                if (count($tnxs) == 7) {
                    $hasInviter = true;
                }

                try {
                    /*
                    * This action considere as Credit to Receipient
                    */
                    // 2. RECIPIENT: Credit To Account ----- Index 1
                    $transUniqueId = self::generateUUID();
                    if (isset($tnxs[1]['fromid'])) {
                        $senderId = $tnxs[1]['fromid'];
                        $recipientId = $tnxs[1]['userid'];
                        $amount = $tnxs[1]['numbers'];

                        $this->createAndSaveTransaction($transRepo, [
                            'operationid' => $transUniqueId,
                            'transactiontype' => 'transferSenderToRecipient',
                            'senderid' => $senderId,
                            'recipientid' => $recipientId,
                            'tokenamount' => $amount,
                            // 'message' => $message,
                            'transferaction' => 'CREDIT',
                            'createdat' => $tnxs[1]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u')
                        ]);
                    }

                    /**
                     * If current user was Invited by any Inviter than Current User has to pay 1% fee to Inviter
                     * 
                     * Consider this actions as a Transactions and Credit fees to Inviter'account
                     */
                    if ($hasInviter && isset($tnxs[2]['fromid'])) {
                        $senderId = $tnxs[2]['fromid'];
                        $recipientId = $tnxs[2]['userid'];
                        $amount = $tnxs[2]['numbers'];
                        $createdat = $tnxs[2]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                        $this->createAndSaveTransaction($transRepo, [
                            'operationid' => $transUniqueId,
                            'transactiontype' => 'transferSenderToInviter',
                            'senderid' => $senderId,
                            'recipientid' => $recipientId,
                            'tokenamount' => $amount,
                            'transferaction' => 'INVITER_FEE',
                            'createdat' => $createdat
                        ]);
                    }

                    /**
                     * 1% Pool Fees will be charged when a Token Transfer happen
                     * 
                     * Credits 1% fees to Pool's Account
                     */
                    if ($hasInviter && isset($tnxs[4]['fromid'])) {
                        $senderId = $tnxs[4]['fromid'];
                        $recipientId = $tnxs[4]['userid'];
                        $amount = $tnxs[4]['numbers'];
                        $createdat = $tnxs[4]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    } else {
                        $senderId = $tnxs[3]['fromid'];
                        $recipientId = $tnxs[3]['userid'];
                        $amount = $tnxs[3]['numbers'];
                        $createdat = $tnxs[3]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToPoolWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId,
                        'tokenamount' => $amount,
                        'transferaction' => 'POOL_FEE',
                        'createdat' => $createdat
                    ]);

                    /**
                     * 2% of requested tokens Peer Fees will be charged 
                     * 
                     * Credits 2% fees to Peer's Account
                     */
                    if ($hasInviter && isset($tnxs[5]['fromid'])) {
                        $senderId = $tnxs[5]['fromid'];
                        $recipientId = ($tnxs[5]['userid']); // Requested tokens
                        $amount = $tnxs[5]['numbers'];
                        $createdat = $tnxs[5]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    } else {
                        $senderId = $tnxs[4]['fromid'];
                        $recipientId = ($tnxs[4]['userid']); // Requested tokens
                        $amount = $tnxs[4]['numbers'];
                        $createdat = $tnxs[4]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToPeerWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId,
                        'tokenamount' => $amount,
                        'transferaction' => 'PEER_FEE',
                        'createdat' => $createdat
                    ]);

                    /**
                     * 1% of requested tokens will be transferred to Burn' account
                     */
                    if ($hasInviter && isset($tnxs[6]['fromid'])) {
                        $senderId = $tnxs[6]['fromid'];
                        $recipientId = ($tnxs[6]['userid']); // Requested tokens
                        $amount = $tnxs[6]['numbers'];
                        $createdat = $tnxs[6]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    } else {
                        if (isset($tnxs[5]['fromid'])) {
                            $senderId = $tnxs[5]['fromid'];
                            $recipientId = ($tnxs[5]['userid']); // Requested tokens
                            $amount = $tnxs[5]['numbers'];
                            $createdat = $tnxs[5]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                        }
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToBurnWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId, // burn accounts for 217+ records not found.
                        'tokenamount' => $amount,
                        'transferaction' => 'BURN_FEE',
                        'createdat' => $createdat
                    ]);

                    $this->updateLogwinStatusInBunch($txnIds, 1);

                    $this->transactionManager->commit();
                } catch (\Throwable $e) {
                    $this->transactionManager->rollback();
                    $this->updateLogwinStatusInBunch($txnIds, 2);

                    continue; // Skip to the next record
                }
                // Update migrated status to 1
            }
            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID ' . $e->getMessage(), 41401);
        }
    }


    /**
     * Records migration for Token Transfers
     * 
     * It will process remaining records which were not processed in first run of migrateTokenTransfer()
     * Used for Peer Token transfer to Receipient (Peer-to-Peer transfer).
     * 
     * 
     * Used for Actions records:
     * whereby = 18 -> Token transfer @deprecated
     * 
     */
    public function migrateTokenTransfer01(): bool
    {
        \ignore_user_abort(true);

        ini_set('max_execution_time', '0');

        $this->logger->info('LogWinMapper.migrateTokenTransfer started');

        try {

            // Group by fromid to avoid duplicates
            $sql = "WITH ordered AS (
                        SELECT
                            lw.*,
                            CASE
                            WHEN lag(lw.createdat) OVER (PARTITION BY lw.fromid ORDER BY lw.createdat) IS NULL THEN 1
                            WHEN lw.createdat - lag(lw.createdat) OVER (PARTITION BY lw.fromid ORDER BY lw.createdat) > INTERVAL '1 second' THEN 1
                            ELSE 0
                            END AS is_new_group
                        FROM logwins lw
                        WHERE lw.migrated = 0
                            AND lw.whereby = 18
                        ),
                        grp AS (
                        SELECT
                            *,
                            sum(is_new_group) OVER (PARTITION BY fromid ORDER BY createdat
                                                    ROWS UNBOUNDED PRECEDING) AS grp_no
                        FROM ordered
                        )
                        SELECT
                        fromid,
                        date_trunc('second', min(createdat)) AS created_at_group,  -- representative timestamp
                        json_agg(row_to_json(grp) ORDER BY createdat)     AS logwin_entries,
                        count(*)                                         AS logwin_count
                        FROM grp
                        GROUP BY fromid, grp_no
                        HAVING count(*) IN (6, 7)
                        ORDER BY created_at_group ASC, fromid
                        LIMIT 50;
                ";


            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $logwins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($logwins)) {
                $this->logger->info('No logwins to migrate');
                return true;
            }

            $this->initializeLiquidityPool();
            $transRepo = new TransactionRepository($this->logger, $this->db);


            foreach ($logwins as $key => $value) {
                $this->transactionManager->beginTransaction();

                $tnxs = json_decode($value['logwin_entries'], true);

                $txnIds = array_column($tnxs, 'token');

                if (empty($tnxs)) {
                    continue;
                }

                $hasInviter = false;
                if (count($tnxs) == 7) {
                    $hasInviter = true;
                }

                try {
                    /*
                    * This action considere as Credit to Receipient
                    */
                    // 2. RECIPIENT: Credit To Account ----- Index 1
                    $transUniqueId = self::generateUUID();
                    if (isset($tnxs[1]['fromid'])) {
                        $senderId = $tnxs[1]['fromid'];
                        $recipientId = $tnxs[1]['userid'];
                        $amount = $tnxs[1]['numbers'];

                        $this->createAndSaveTransaction($transRepo, [
                            'operationid' => $transUniqueId,
                            'transactiontype' => 'transferSenderToRecipient',
                            'senderid' => $senderId,
                            'recipientid' => $recipientId,
                            'tokenamount' => $amount,
                            // 'message' => $message,
                            'transferaction' => 'CREDIT',
                            'createdat' => $tnxs[1]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u')
                        ]);
                    }

                    /**
                     * If current user was Invited by any Inviter than Current User has to pay 1% fee to Inviter
                     * 
                     * Consider this actions as a Transactions and Credit fees to Inviter'account
                     */
                    if ($hasInviter && isset($tnxs[2]['fromid'])) {
                        $senderId = $tnxs[2]['fromid'];
                        $recipientId = $tnxs[2]['userid'];
                        $amount = $tnxs[2]['numbers'];
                        $createdat = $tnxs[2]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                        $this->createAndSaveTransaction($transRepo, [
                            'operationid' => $transUniqueId,
                            'transactiontype' => 'transferSenderToInviter',
                            'senderid' => $senderId,
                            'recipientid' => $recipientId,
                            'tokenamount' => $amount,
                            'transferaction' => 'INVITER_FEE',
                            'createdat' => $createdat
                        ]);
                    }

                    /**
                     * 1% Pool Fees will be charged when a Token Transfer happen
                     * 
                     * Credits 1% fees to Pool's Account
                     */
                    if ($hasInviter && isset($tnxs[4]['fromid'])) {
                        $senderId = $tnxs[4]['fromid'];
                        $recipientId = $tnxs[4]['userid'];
                        $amount = $tnxs[4]['numbers'];
                        $createdat = $tnxs[4]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    } else {
                        $senderId = $tnxs[3]['fromid'];
                        $recipientId = $tnxs[3]['userid'];
                        $amount = $tnxs[3]['numbers'];
                        $createdat = $tnxs[3]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToPoolWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId,
                        'tokenamount' => $amount,
                        'transferaction' => 'POOL_FEE',
                        'createdat' => $createdat
                    ]);

                    /**
                     * 2% of requested tokens Peer Fees will be charged 
                     * 
                     * Credits 2% fees to Peer's Account
                     */
                    if ($hasInviter && isset($tnxs[5]['fromid'])) {
                        $senderId = $tnxs[5]['fromid'];
                        $recipientId = ($tnxs[5]['userid']); // Requested tokens
                        $amount = $tnxs[5]['numbers'];
                        $createdat = $tnxs[5]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    } else {
                        $senderId = $tnxs[4]['fromid'];
                        $recipientId = ($tnxs[4]['userid']); // Requested tokens
                        $amount = $tnxs[4]['numbers'];
                        $createdat = $tnxs[4]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToPeerWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId,
                        'tokenamount' => $amount,
                        'transferaction' => 'PEER_FEE',
                        'createdat' => $createdat
                    ]);

                    /**
                     * 1% of requested tokens will be transferred to Burn' account
                     */
                    if ($hasInviter && isset($tnxs[6]['fromid'])) {
                        $senderId = $tnxs[6]['fromid'];
                        $recipientId = ($tnxs[6]['userid']); // Requested tokens
                        $amount = $tnxs[6]['numbers'];
                        $createdat = $tnxs[6]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    } else {
                        if (isset($tnxs[5]['fromid'])) {
                            $senderId = $tnxs[5]['fromid'];
                            $recipientId = ($tnxs[5]['userid']); // Requested tokens
                            $amount = $tnxs[5]['numbers'];
                            $createdat = $tnxs[5]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                        }
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToBurnWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId, // burn accounts for 217+ records not found.
                        'tokenamount' => $amount,
                        'transferaction' => 'BURN_FEE',
                        'createdat' => $createdat
                    ]);

                    $this->updateLogwinStatusInBunch($txnIds, 1);

                    $this->transactionManager->commit();
                } catch (\Throwable $e) {
                    $this->transactionManager->rollback();
                    $this->updateLogwinStatusInBunch($txnIds, 2);

                    continue; // Skip to the next record
                }
                // Update migrated status to 1
            }
            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID ' . $e->getMessage(), 41401);
        }
    }


    /**
     * Generate logwins entries for Like actions between 05th March 2025 to 02nd April 2025
     *
     * Used for Actions records:
     *  whereby = 2  -> Post Like
     */
    public function generateLikePaidActionToLogWins(): bool
    {
        \ignore_user_abort(true);

        ini_set('max_execution_time', '0');

        try {

            $sql = "WITH ranked AS (
                        SELECT 
                            upl.*,
                            ROW_NUMBER() OVER (
                                PARTITION BY userid, DATE(createdat) 
                                ORDER BY createdat
                            ) AS rn
                        FROM user_post_likes upl
                        WHERE  createdat < '2025-04-02 08:31'
                    )
                    SELECT *
                    FROM ranked
                    WHERE rn > 3
                    ORDER BY createdat;
                ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $logwins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if(empty($logwins)) {
                $this->logger->info('No logwins to migrate');
                return true;
            }

            $this->initializeLiquidityPool();
            $transRepo = new TransactionRepository($this->logger, $this->db);

            
            $sql = "INSERT INTO logwins 
                    (token, userid, postid, fromid, gems, numbers, numbersq, whereby, createdat) 
                    VALUES 
                    (:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :createdat)";


            $tokenIds = [];
            foreach ($logwins as $key => $value) {
                $this->transactionManager->beginTransaction();


                try {
                    $stmt = $this->db->prepare($sql);

                    $tokenId = self::generateUUID();

                    $numBers = -3; // Each extra like will cost 3 Gems

                    $userId = $value['userid'];
                    $createdat = $value['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    $stmt->bindValue(':token', $tokenId, \PDO::PARAM_STR);
                    $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                    $stmt->bindValue(':postid', $value['postid'], \PDO::PARAM_STR);
                    $stmt->bindValue(':fromid', $userId, \PDO::PARAM_STR);
                    $stmt->bindValue(':gems', 0, \PDO::PARAM_STR);
                    $stmt->bindValue(':numbers', $numBers, \PDO::PARAM_STR);
                    $stmt->bindValue(':numbersq', $this->decimalToQ64_96($numBers), \PDO::PARAM_STR); // 29 char precision
                    $stmt->bindValue(':whereby', 2, \PDO::PARAM_INT);
                    $stmt->bindValue(':createdat', $createdat);

                    $stmt->execute();


                    $transactionType = 'postLiked';
                    $transferType = 'BURN';

                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $tokenId,
                        'transactiontype' => $transactionType,
                        'senderid' => $userId,
                        'recipientid' => $this->burnWallet,
                        'tokenamount' => $numBers,
                        'transferaction' => $transferType,
                        'createdat' => $createdat
                    ]);

                    $this->saveWalletEntry($this->burnWallet, -$numBers);


                    $this->transactionManager->commit();
                    $tokenIds[] = $tokenId;

                } catch (\Throwable $e) {
                    $this->transactionManager->rollback();
                    $this->updateLogwinStatus($tokenId, 2); // Unmigrated due to some errors
                    continue;
                }
            }

            $this->updateLogwinStatusInBunch($tokenIds, 1);

            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID', 41401);
        }
    }


    /**
     * Update wallet balance for users whose has difference in logwin entries and wallet balance
     *
     */
    public function logWinMigrationWalletUpdate(): bool
    {

        \ignore_user_abort(true);

        ini_set('max_execution_time', '0');

        try {

            $sql = "SELECT u.uid, u.username, w.liquidity, 
                        (SELECT sum(numbers) FROM logwins l WHERE l.userid = u.uid) AS logwin_total, 
                        (
                        SELECT		    
                            COALESCE(SUM(CASE WHEN t.recipientid = u.uid THEN t.tokenamount END), 0)
                        - COALESCE(SUM(CASE WHEN t.senderid = u.uid THEN ABS(t.tokenamount) END), 0) AS net_balance
                        FROM transactions t
                        ) AS transaction_total,
                        (
                        (SELECT sum(numbers) FROM logwins l WHERE l.userid = u.uid)
                        - (
                        SELECT		    
                            COALESCE(SUM(CASE WHEN t.recipientid = u.uid THEN t.tokenamount END), 0)
                        - COALESCE(SUM(CASE WHEN t.senderid = u.uid THEN ABS(t.tokenamount) END), 0) AS net_balance
                        FROM transactions t
                        )) AS logwind_tnx_diff,
                        (
                            (SELECT (SUM(numbers)) FROM logwins l WHERE l.userid = u.uid) - w.liquidity
                        ) AS logwins_balance_diff,
                        u.createdat 
                    FROM users u 
                        LEFT JOIN wallett w ON u.uid = w.userid 
                    ORDER BY u.createdat ASC LIMIT 500;
                    ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $balances = $stmt->fetchAll(\PDO::FETCH_ASSOC);


            
            $this->initializeLiquidityPool();
            $transRepo = new TransactionRepository($this->logger, $this->db);

            foreach ($balances as $key => $balance) {

                if (empty($balance['logwins_balance_diff']) || $balance['logwins_balance_diff'] == 0 || $balance['logwin_total'] < 0) {
                    continue;
                }

                $this->transactionManager->beginTransaction();

                try {
                    $numBers = $balance['logwins_balance_diff'];
                    $recipientid = $balance['uid'];

                    $operationid = self::generateUUID();
                    $senderid = $this->companyWallet;
                    $createdat = (new DateTime())->format('Y-m-d H:i:s.u');

                    $transactionType = 'walletAdjustment';
                    $transferaction = 'ADJUSTMENT';

                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $operationid,
                        'transactiontype' => $transactionType,
                        'senderid' => $senderid,
                        'recipientid' => $recipientid,
                        'tokenamount' => 0,
                        'transferaction' => $transferaction,
                        'message' => 'Wallet adjustment as records mismatch - ' . $balance['logwins_balance_diff'],
                        'createdat' => $createdat
                    ]);

                    $this->saveWalletEntry($recipientid, $numBers);

                    $this->transactionManager->commit();

                } catch (\Throwable $e) {
                    $this->logger->error('Failed to update wallet for user: ' . $balance['username'], ['exception' => $e->getMessage()]);
                    $this->transactionManager->rollback();
                    continue;
                }
            }

            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID', 41401);
        }
    }

    /**
     * Update wallet balance to Zero for those users whose wallet balance is negative
     *
     */
    public function logWinMigrationWalletNegativeToZero(): bool
    {

        \ignore_user_abort(true);

        ini_set('max_execution_time', '0');

        try {

            $negativeUsersBal = ['a2dd49d4-d79d-45f6-871c-f2533521249f', '78c359ab-2cc8-43da-959d-2c4f453bdfb8', '9e4d646f-8085-4032-b7ad-d667bbc0fa9b', 'de0f6f8a-9ea7-4e3f-8412-d8d78d84c422', '1fb403b1-129e-4d8a-8d29-8900fa2bd048'];

            
            $this->initializeLiquidityPool();
            $transRepo = new TransactionRepository($this->logger, $this->db);

            foreach ($negativeUsersBal as $key => $userId) {

                $this->transactionManager->beginTransaction();

                try {
                    $numBers = 0;
                    $recipientid = $userId;

                    $operationid = self::generateUUID();
                    $senderid = $this->companyWallet;
                    $createdat = (new DateTime())->format('Y-m-d H:i:s.u');

                    $transactionType = 'walletAdjustment';
                    $transferaction = 'ADJUSTMENT';

                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $operationid,
                        'transactiontype' => $transactionType,
                        'senderid' => $senderid,
                        'recipientid' => $recipientid,
                        'tokenamount' => 0,
                        'transferaction' => $transferaction,
                        'message' => 'Wallet adjustment as Records were negative. Setting balance to Zero',
                        'createdat' => $createdat
                    ]);


                    $liquiditq = ((float) $this->decimalToQ64_96($numBers));

                    $query = "UPDATE wallett
                          SET liquidity = :liquidity, liquiditq = :liquiditq, updatedat = :updatedat
                                WHERE userid = :userid";
                    $stmt = $this->db->prepare($query);
                    $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                    $stmt->bindValue(':liquidity', 0, \PDO::PARAM_STR);
                    $stmt->bindValue(':liquiditq', $liquiditq, \PDO::PARAM_STR);
                    $stmt->bindValue(':updatedat', (new \DateTime())->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);

                    $stmt->execute();
                        
                    $this->transactionManager->commit();

                } catch (\Throwable $e) {
                    $this->logger->error('Failed to update wallet for user: ' . $userId, ['exception' => $e->getMessage()]);
                    $this->transactionManager->rollback();
                    continue;
                }
            }

            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID', 41401);
        }
    }


    /**
     * Generate logwins entries for Dislike actions between 05th March 2025 to 02nd April 2025
     *
     * Used for Actions records:
     *  whereby = 3  -> Post Dislike
     */
    public function generateDislikePaidActionToLogWins(): bool
    {
        \ignore_user_abort(true);

        ini_set('max_execution_time', '0');

        try {

            $sql = "WITH ranked AS (
                        SELECT 
                            upl.*,
                            ROW_NUMBER() OVER (
                                PARTITION BY userid, DATE(createdat) 
                                ORDER BY createdat
                            ) AS rn
                        FROM user_post_dislikes upl
                        WHERE  createdat < '2025-04-02 08:31'
                    )
                    SELECT *
                    FROM ranked
                    WHERE rn > 0
                    ORDER BY createdat;
                ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $logwins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if(empty($logwins)) {
                $this->logger->info('No logwins to migrate');
                return true;
            }
            $this->initializeLiquidityPool();
            $transRepo = new TransactionRepository($this->logger, $this->db);

            $tokenIds = [];
            foreach ($logwins as $key => $value) {
                $this->transactionManager->beginTransaction();

                $sql = "INSERT INTO logwins 
                (token, userid, postid, fromid, gems, numbers, numbersq, whereby, createdat) 
                VALUES 
                (:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :createdat)";

                try {
                    $stmt = $this->db->prepare($sql);

                    $tokenId = self::generateUUID();

                    $numBers = -5; // Each extra dislike will cost 5 Gems

                    $userId = $value['userid'];
                    $createdat = $value['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    $stmt->bindValue(':token', $tokenId, \PDO::PARAM_STR);
                    $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                    $stmt->bindValue(':postid', $value['postid'], \PDO::PARAM_STR);
                    $stmt->bindValue(':fromid', $userId, \PDO::PARAM_STR);
                    $stmt->bindValue(':gems', 0, \PDO::PARAM_STR);
                    $stmt->bindValue(':numbers', $numBers, \PDO::PARAM_STR);
                    $stmt->bindValue(':numbersq', $this->decimalToQ64_96($numBers), \PDO::PARAM_STR); // 29 char precision
                    $stmt->bindValue(':whereby', 3, \PDO::PARAM_INT);
                    $stmt->bindValue(':createdat', $createdat);

                    $stmt->execute();


                    $transactionType = 'postDisliked';
                    $transferType = 'BURN';

                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $tokenId,
                        'transactiontype' => $transactionType,
                        'senderid' => $userId,
                        'recipientid' => $this->burnWallet,
                        'tokenamount' => $numBers,
                        'transferaction' => $transferType,
                        'createdat' => $createdat
                    ]);

                    $this->saveWalletEntry($this->burnWallet, -$numBers);


                    $this->transactionManager->commit();
                    $tokenIds[] = $tokenId;
                } catch (\Throwable $e) {
                    $this->transactionManager->rollback();
                    $this->updateLogwinStatus($tokenId, 2); // Unmigrated due to some errors
                    continue;
                }
            }

            $this->updateLogwinStatusInBunch($tokenIds, 1);

            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID', 41401);
        }
    }


    /**
     * Generate logwins entries for Post creation actions between 05th March 2025 to 02nd April 2025
     *
     * Used for Actions records:
     *  whereby = 5  -> Post Creation
     */
    public function generatePostPaidActionToLogWins(): bool
    {
        \ignore_user_abort(true);

        ini_set('max_execution_time', '0');

        try {

            $sql = "WITH ranked AS (
                        SELECT 
                            upl.*,
                            ROW_NUMBER() OVER (
                                PARTITION BY userid, DATE(createdat) 
                                ORDER BY createdat
                            ) AS rn
                        FROM posts upl
                        WHERE  createdat < '2025-04-02 08:31'
                    )
                    SELECT *
                    FROM ranked
                    WHERE rn > 1
                    ORDER BY createdat;
                ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $logwins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if(empty($logwins)) {
                $this->logger->info('No logwins to migrate');
                return true;
            }

            $this->initializeLiquidityPool();
            $transRepo = new TransactionRepository($this->logger, $this->db);

            $tokenIds = [];
            foreach ($logwins as $key => $value) {
                $this->transactionManager->beginTransaction();

                $sql = "INSERT INTO logwins 
                (token, userid, postid, fromid, gems, numbers, numbersq, whereby, createdat) 
                VALUES 
                (:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :createdat)";

                try {
                    $stmt = $this->db->prepare($sql);

                    $tokenId = self::generateUUID();

                    $numBers = -20; // Each extra like will cost 3 Gems

                    $userId = $value['userid'];
                    $createdat = $value['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    $stmt->bindValue(':token', $tokenId, \PDO::PARAM_STR);
                    $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                    $stmt->bindValue(':postid', $value['postid'], \PDO::PARAM_STR);
                    $stmt->bindValue(':fromid', $userId, \PDO::PARAM_STR);
                    $stmt->bindValue(':gems', 0, \PDO::PARAM_STR);
                    $stmt->bindValue(':numbers', $numBers, \PDO::PARAM_STR);
                    $stmt->bindValue(':numbersq', $this->decimalToQ64_96($numBers), \PDO::PARAM_STR); // 29 char precision
                    $stmt->bindValue(':whereby', 5, \PDO::PARAM_INT);
                    $stmt->bindValue(':createdat', $createdat);

                    $stmt->execute();


                    $transactionType = 'postCreated';
                    $transferType = 'BURN';

                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $tokenId,
                        'transactiontype' => $transactionType,
                        'senderid' => $userId,
                        'recipientid' => $this->burnWallet,
                        'tokenamount' => $numBers,
                        'transferaction' => $transferType,
                        'createdat' => $createdat
                    ]);

                    $this->saveWalletEntry($this->burnWallet, -$numBers);


                    $this->transactionManager->commit();
                    $tokenIds[] = $tokenId;
                } catch (\Throwable $e) {
                    $this->transactionManager->rollback();
                    $this->updateLogwinStatus($tokenId, 2); // Unmigrated due to some errors
                    continue;
                }
            }

            $this->updateLogwinStatusInBunch($tokenIds, 1);

            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID', 41401);
        }
    }


    /**
     * Generate logwins entries for Post creation actions between 05th March 2025 to 02nd April 2025
     *
     * Used for Actions records:
     *  whereby = 4  -> Post Comment
     */
    public function generateCommentPaidActionToLogWins(): bool
    {
        \ignore_user_abort(true);

        ini_set('max_execution_time', '0');

        try {

            $sql = "WITH ranked AS (
                        SELECT 
                            upl.*,
                            ROW_NUMBER() OVER (
                                PARTITION BY userid, DATE(createdat) 
                                ORDER BY createdat
                            ) AS rn
                        FROM user_post_comments upl
                        WHERE  createdat < '2025-04-02 08:31'
                    )
                    SELECT *
                    FROM ranked
                    WHERE rn > 4
                    ORDER BY createdat;
                ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $logwins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if(empty($logwins)) {
                $this->logger->info('No logwins to migrate');
                return true;
            }

            $this->initializeLiquidityPool();
            $transRepo = new TransactionRepository($this->logger, $this->db);

            $tokenIds = [];
            foreach ($logwins as $key => $value) {
                $this->transactionManager->beginTransaction();

                $sql = "INSERT INTO logwins 
                (token, userid, postid, fromid, gems, numbers, numbersq, whereby, createdat) 
                VALUES 
                (:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :createdat)";

                try {
                    $stmt = $this->db->prepare($sql);

                    $tokenId = self::generateUUID();

                    $numBers = -0.5; // Each extra like will cost 0.5 Gems

                    $userId = $value['userid'];
                    $createdat = $value['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    $stmt->bindValue(':token', $tokenId, \PDO::PARAM_STR);
                    $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                    $stmt->bindValue(':postid', $value['commentid'], \PDO::PARAM_STR);
                    $stmt->bindValue(':fromid', $userId, \PDO::PARAM_STR);
                    $stmt->bindValue(':gems', 0, \PDO::PARAM_STR);
                    $stmt->bindValue(':numbers', $numBers, \PDO::PARAM_STR);
                    $stmt->bindValue(':numbersq', $this->decimalToQ64_96($numBers), \PDO::PARAM_STR); // 29 char precision
                    $stmt->bindValue(':whereby', 4, \PDO::PARAM_INT);
                    $stmt->bindValue(':createdat', $createdat);

                    $stmt->execute();


                    $transactionType = 'postComment';
                    $transferType = 'BURN';

                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $tokenId,
                        'transactiontype' => $transactionType,
                        'senderid' => $userId,
                        'recipientid' => $this->burnWallet,
                        'tokenamount' => $numBers,
                        'transferaction' => $transferType,
                        'createdat' => $createdat
                    ]);

                    $this->saveWalletEntry($this->burnWallet, -$numBers);


                    $this->transactionManager->commit();
                    $tokenIds[] = $tokenId;
                } catch (\Throwable $e) {
                    $this->transactionManager->rollback();
                    $this->updateLogwinStatus($tokenId, 2); // Unmigrated due to some errors
                    continue;
                }
            }

            $this->updateLogwinStatusInBunch($tokenIds, 1);

            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID', 41401);
        }
    }

    /**
     * Generate logwins entries from gems table between 05th March 2025 to 02nd April 2025
     * 
     * Gems table: Records are pending to be added on logwins: Around Total: 1666
     * 
     * Date From: 
     * "2025-03-05 15:48:24.08886"										
     * Date To:
     * "2025-04-02 02:13:11.124195"	
     */
    public function migrateGemsToLogWins(): bool
    {
        \ignore_user_abort(true);
        ini_set('max_execution_time', '0');

        $this->logger->info('LogWinMapper.migrateTokenTransfer started');

        $sql = "
            SELECT 
                DATE(g.createdat) AS created_date,
                COUNT(*) AS total_gems
            FROM gems g
            WHERE g.createdat <= '2025-04-02'
            AND NOT EXISTS (
                SELECT 1
                FROM logwins l
                WHERE g.postid = l.postid
                    AND g.fromid = l.fromid
                    AND g.whereby = l.whereby
                    AND g.userid = l.userid
            )
            GROUP BY DATE(g.createdat)
            ORDER BY created_date ASC;
        ";
        $this->logger->info('LogWinMapper.migrateGemsToLogWins SQL', ['sql' => $sql]);

        $stmt = $this->db->query($sql);
        $gemDays = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($gemDays)) {
            $this->logger->info('No gems to migrate to logwins');
            return true;
        }

        // Initialize heavy dependencies only once
        $this->initializeLiquidityPool();
        $transRepo = new TransactionRepository($this->logger, $this->db);
        
        
        // Prepare insert queries only once
        $sqlLogWins = "INSERT INTO logwins 
            (token, userid, postid, fromid, gems, numbers, numbersq, whereby, createdat) 
            VALUES 
            (:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :createdat)";

        $sqlWallet = "INSERT INTO wallet 
            (token, userid, postid, fromid, numbers, numbersq, whereby, createdat) 
            VALUES 
            (:token, :userid, :postid, :fromid, :numbers, :numbersq, :whereby, :createdat)";

        $stmtLogWins = $this->db->prepare($sqlLogWins);
        $stmtWallet = $this->db->prepare($sqlWallet);


        foreach ($gemDays as $key => $day) {

            $data = json_decode($day['gem_entries'], true);

            $sql = "
                WITH user_sums AS (
                    SELECT 
                        userid,
                        GREATEST(SUM(gems), 0) AS total_numbers
                    FROM gems
                    WHERE gems > 0 AND  createdat::date = '{$day['created_date']}' AND collected = 0
                    GROUP BY userid
                ),
                total_sum AS (
                    SELECT SUM(total_numbers) AS overall_total FROM user_sums
                )
                SELECT 
                    g.userid,
                    g.gemid,
                    g.postid,
                    g.fromid,
                    g.gems,
                    g.whereby,
                    g.createdat,
                    us.total_numbers,
                    (SELECT SUM(total_numbers) FROM user_sums) AS overall_total,
                    (us.total_numbers * 100.0 / ts.overall_total) AS percentage
                FROM gems g
                JOIN user_sums us ON g.userid = us.userid
                CROSS JOIN total_sum ts
                WHERE us.total_numbers > 0 AND collected = 0 AND createdat::date = '{$day['created_date']}';
            ";

            try {
                $stmt = $this->db->query($sql);
                $gemAllDays = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                
                $totalGems = isset($gemAllDays[0]['overall_total']) ? (string)$gemAllDays[0]['overall_total'] : '0';
                $dailyToken = DAILY_NUMBER_TOKEN;

                $gemsintoken = TokenHelper::divRc((float)$dailyToken, (float)$totalGems);
                $bestatigungInitial = TokenHelper::mulRc((float)$totalGems, (float)$gemsintoken);

                $args = [
                    'winstatus' => [
                        'totalGems' => $totalGems,
                        'gemsintoken' => $gemsintoken,
                        'bestatigung' => $bestatigungInitial
                    ]
                ];

                if(empty($gemAllDays)) {
                    $this->logger->info('No gems to migrate for date', ['date' => $day['created_date']]);
                    continue;
                }
                try {
                    $this->db->beginTransaction();

                    foreach ($gemAllDays as $row) {
                        $userId = (string)$row['userid'];


                        if($row['gems'] <= 0) {
                            continue;
                        }

                        $rowgems2token = TokenHelper::mulRc((float) $row['gems'], (float) $gemsintoken);

                        $args = [
                            'gemid' => $row['gemid'],
                            'userid' => $row['userid'],
                            'postid' => $row['postid'],
                            'fromid' => $row['fromid'],
                            'gems' => $row['gems'],
                            'numbers' => $rowgems2token,
                            'whereby' => $row['whereby'],
                            'createdat' => $row['createdat']
                        ];

                        $postId = $args['postid'] ?? null;
                        $fromId = $args['fromid'] ?? null;
                        $gems = $args['gems'] ?? 0.0;
                        $numBers = $rowgems2token ?? 0;
                        $createdat =  $args['createdat'] ?? (new \DateTime())->format('Y-m-d H:i:s.u');

                        try {
                            // Insert into logwins
                            $tokenId = self::generateUUID();
                            $stmtLogWins->bindValue(':token', $tokenId, \PDO::PARAM_STR);
                            $stmtLogWins->bindValue(':userid', $userId, \PDO::PARAM_STR);
                            $stmtLogWins->bindValue(':postid', $postId, \PDO::PARAM_STR);
                            $stmtLogWins->bindValue(':fromid', $fromId, \PDO::PARAM_STR);
                            $stmtLogWins->bindValue(':gems', $gems, \PDO::PARAM_STR);
                            $stmtLogWins->bindValue(':numbers', $numBers, \PDO::PARAM_STR);
                            $stmtLogWins->bindValue(':numbersq', $this->decimalToQ64_96($numBers), \PDO::PARAM_STR);
                            $stmtLogWins->bindValue(':whereby', $args['whereby'], \PDO::PARAM_INT);
                            $stmtLogWins->bindValue(':createdat', $createdat, \PDO::PARAM_STR);
                            $stmtLogWins->execute();
                            $stmtLogWins->closeCursor();

                            // Create transaction entry
                            $transactionType = match ($args['whereby']) {
                                1 => 'postViewed',
                                2 => 'postLiked',
                                3 => 'postDisLiked',
                                4 => 'postComment',
                                5 => 'postCreated',
                                default => '',
                            };

                            $transferType = ($numBers < 0) ? 'BURN' : 'MINT';

                            $senderid = $userId;
                            if ($numBers < 0) {
                                $transferType = 'BURN';
                                $recipientid = $this->burnWallet;
                            } else {
                                $transferType = 'MINT';
                                $recipientid = $userId;
                                $senderid = $this->companyWallet;
                            }

                            $this->createAndSaveTransaction($transRepo, [
                                'operationid' => $tokenId,
                                'transactiontype' => $transactionType,
                                'senderid' => $senderid,
                                'recipientid' => $recipientid,
                                'tokenamount' => $numBers,
                                'transferaction' => $transferType,
                                'createdat' => $createdat
                            ]);

                            if ($transferType === 'BURN') {
                                $this->saveWalletEntry($this->burnWallet, -$numBers);
                            }

                            $this->logger->info('Inserted into logwins successfully', [
                                'userId' => $userId,
                                'postid' => $postId
                            ]);

                            // Insert into wallet
                            $stmtWallet->bindValue(':token', $this->getPeerToken(), \PDO::PARAM_STR);
                            $stmtWallet->bindValue(':userid', $userId, \PDO::PARAM_STR);
                            $stmtWallet->bindValue(':postid', $postId, \PDO::PARAM_STR);
                            $stmtWallet->bindValue(':fromid', $fromId, \PDO::PARAM_STR);
                            $stmtWallet->bindValue(':numbers', $numBers, \PDO::PARAM_STR);
                            $stmtWallet->bindValue(':numbersq', $this->decimalToQ64_96($numBers), \PDO::PARAM_STR);
                            $stmtWallet->bindValue(':whereby', $args['whereby'], \PDO::PARAM_INT);
                            $stmtWallet->bindValue(':createdat', $createdat, \PDO::PARAM_STR);
                            $stmtWallet->execute();
                            $stmtWallet->closeCursor();

                            $this->logger->info('Inserted into wallet successfully', [
                                'userId' => $userId,
                                'postid' => $postId
                            ]);
                        } catch (\Throwable $e) {
                            $this->logger->error('Error updating gems or liquidity', ['exception' => $e->getMessage()]);
                        }
                    }

                    $this->db->commit();
                } catch (\Throwable $e) {
                    $this->db->rollBack();
                    $this->logger->error('Transaction failed', ['exception' => $e->getMessage()]);
                }

                // Update gems as collected
                try {
                    $gemIds = array_column($gemAllDays, 'gemid');
                    $quotedGemIds = array_map(fn($gemId) => $this->db->quote($gemId), $gemIds);
                    $this->db->query('UPDATE gems SET collected = 1 WHERE gemid IN (' . \implode(',', $quotedGemIds) . ')');
                } catch (\Throwable $e) {
                    $this->logger->error('Error updating gems collected flag', ['exception' => $e->getMessage()]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Error reading gems', ['exception' => $e->getMessage()]);
            }
        }

        return true;
    }


    public function getPeerToken(): string
    {
        $x = 0;
        $token = $this->getToken(12);
        do {
            $stmt = $this->db->prepare('SELECT token FROM wallet WHERE token = ?');
            $stmt->execute([$token]);
            $res = $stmt->fetch(\PDO::FETCH_ASSOC);
        } while ($res && $x++ < 100);
        return $token;
    }

    public function getToken(int $length = 32): string
    {
        $token = '';
        $codeAlphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[$this->crypto_rand_secure(0, \strlen($codeAlphabet))];
        }
        return $token;
    }


    // CRYPTO GENERATOR
    public function crypto_rand_secure(int $min, int $max): int
    {
        $range = $max - $min;
        if ($range < 0) return $min;
        $log = \log($range, 2);
        $bytes = (int)($log / 8) + 1;
        $bits = (int)$log + 1;
        $filter = (int)(1 << $bits) - 1;
        do {
            $rnd = \hexdec(\bin2hex(\openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter;
        } while ($rnd >= $range);
        return $min + $rnd;
    }

    /**
     * Marked as Status migrated
     */
    private function updateLogwinStatus(string $token, int $status): void
    {
        $sql = "UPDATE logwins SET migrated = :migrated WHERE token = :token";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':migrated', $status, \PDO::PARAM_INT);
        $stmt->bindValue(':token', $token, \PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * Marked as Status migrated in bunch
     */
    private function updateLogwinStatusInBunch(array $tokens, int $status): void
    {
        $tokensList = implode("', '", $tokens);

        $sql = "UPDATE logwins SET migrated = :migrated WHERE token IN ('$tokensList')";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':migrated', $status, \PDO::PARAM_INT);
        $stmt->execute();
    }


    /**
     * Helper to create and save a transaction
     */
    private function createAndSaveTransaction($transRepo, array $transObj): void
    {
        $transaction = new Transaction($transObj, ['operationid', 'senderid', 'tokenamount'], false);
        $transRepo->saveTransaction($transaction);
    }


    public function saveWalletEntry(string $userId, float $liquidity): float
    {
        \ignore_user_abort(true);
        $this->logger->info('WalletMapper.saveWalletEntry started');

        try {
            $query = "SELECT 1 FROM wallett WHERE userid = :userid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
            $stmt->execute();
            $userExists = $stmt->fetchColumn();

            if (!$userExists) {
                $newLiquidity = abs($liquidity);
                $liquiditq = ((float)$this->decimalToQ64_96($newLiquidity));

                $query = "INSERT INTO wallett (userid, liquidity, liquiditq, updatedat)
                          VALUES (:userid, :liquidity, :liquiditq, :updatedat)";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                $stmt->bindValue(':liquidity', $newLiquidity, \PDO::PARAM_STR);
                $stmt->bindValue(':liquiditq', $liquiditq, \PDO::PARAM_STR);
                $stmt->bindValue(':updatedat', (new \DateTime())->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);

                $stmt->execute();
            } else {
                $currentBalance = $this->getUserWalletBalance($userId);
                $newLiquidity = TokenHelper::addRc($currentBalance, $liquidity);
                $liquiditq = ((float)$this->decimalToQ64_96($newLiquidity));

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

            $this->logger->info('Wallet entry saved successfully', ['newLiquidity' => $newLiquidity]);
            $this->updateUserLiquidity($userId, $newLiquidity);

            return $newLiquidity;
        } catch (\Throwable $e) {
            $this->logger->error('Database error in saveWalletEntry: ' . $e->getMessage());
            throw new \RuntimeException('Unable to save wallet entry');
        }
    }


    public function updateUserLiquidity(string $userId, float $liquidity): bool
    {
        try {

            $sqlUpdate = "UPDATE users_info SET liquidity = :liquidity, updatedat = CURRENT_TIMESTAMP WHERE userid = :userid";
            $stmt = $this->db->prepare($sqlUpdate);

            $stmt->bindValue(':liquidity', $liquidity, \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);

            $stmt->execute();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Error updating liquidity', ['exception' => $e->getMessage(), 'userid' => $userId]);
            return false;
        }
    }


    private function decimalToQ64_96(float $value): string
    {
        $scaleFactor = \bcpow('2', '96');

        // Convert float to plain decimal string 
        $decimalString = \number_format($value, 30, '.', ''); // 30 decimal places should be enough

        $scaledValue = \bcmul($decimalString, $scaleFactor, 0);

        return $scaledValue;
    }


    public function getUserWalletBalance(string $userId): float
    {
        $this->logger->info('WalletMapper.getUserWalletBalance started');

        $query = "SELECT COALESCE(liquidity, 0) AS balance 
                  FROM wallett 
                  WHERE userid = :userId";

        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->execute();
            $balance = $stmt->fetchColumn();

            $this->logger->info('Fetched wallet balance', ['balance' => $balance]);

            return (float) $balance;
        } catch (\PDOException $e) {
            $this->logger->error('Database error in getUserWalletBalance: ' . $e->getMessage());
            throw new \RuntimeException('Unable to fetch wallet balance');
        }
    }
}
