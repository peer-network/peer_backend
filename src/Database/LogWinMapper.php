<?php

namespace Fawaz\Database;

use DateTime;
use Fawaz\App\Models\Transaction;
use Fawaz\App\Models\TransactionCategory;
use Fawaz\App\Repositories\TransactionRepository;
use Fawaz\Database\Interfaces\TransactionManager;
use PDO;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use Psr\Log\LoggerInterface;


class LogWinMapper
{
    use ResponseHelper;


    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db, protected LiquidityPool $pool, protected TransactionManager $transactionManager) {}

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
                    HAVING COUNT(*) IN (5, 6, 7)
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

            $transRepo = new TransactionRepository($this->logger, $this->db);


            foreach ($logwins as $key => $value) {
                $this->transactionManager->beginTransaction();

                $tnxs = json_decode($value['logwin_entries'], true);

                $txnIds = array_column($tnxs, 'token');

                if (empty($tnxs)) {
                    continue;
                }

                $hasInviter = false;
                $withoutFeeDeduction = false;
                if (count($tnxs) == 7) {
                    $hasInviter = true;
                }

                if (count($tnxs) == 5) {
                    $withoutFeeDeduction = true;
                }
                try {
                    $inviterAmount = null;
                    $poolFeeAmount = null;
                    $peerFeeAmount = null;
                    $burnFeeAmount = null;
                    /*
                    * This action considere as Credit to Receipient
                    */
                    // 2. RECIPIENT: Credit To Account ----- Index 1
                    $transUniqueId = self::generateUUID();
                    if (isset($tnxs[1]['fromid'])) {
                        $senderId = $tnxs[1]['fromid'];
                        $recipientId = $tnxs[1]['userid'];

                        $this->createAndSaveTransaction($transRepo, [
                            'operationid' => $transUniqueId,
                            'transactiontype' => 'transferSenderToRecipient',
                            'transactioncategory' => TransactionCategory::P2P_TRANSFER->value,
                            'senderid' => $senderId,
                            'recipientid' => $recipientId,
                            'tokenamount' => $tnxs[1]['numbers'],
                            // 'message' => $message,
                            'transferaction' => 'CREDIT',
                            'createdat' => $tnxs[1]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u')
                        ]);

                        $saveForLogs = [
                            'operationid' => $transUniqueId,
                            'transactiontype' => 'transferSenderToRecipient',
                            'transactioncategory' => TransactionCategory::P2P_TRANSFER->value,
                            'senderid' => $senderId,
                            'recipientid' => $recipientId,
                            'tokenamount' => $tnxs[1]['numbers'],
                            // 'message' => $message,
                            'transferaction' => 'CREDIT',
                            'createdat' => $tnxs[1]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u')
                        ];
                    }

                    /**
                     * If current user was Invited by any Inviter than Current User has to pay 1% fee to Inviter
                     * 
                     * Consider this actions as a Transactions and Credit fees to Inviter'account
                     */
                    if ($hasInviter && isset($tnxs[2]['fromid'])) {
                        $senderId = $tnxs[2]['fromid'];
                        $recipientId = $tnxs[2]['userid'];
                        $inviterAmount = $tnxs[2]['numbers'];
                        $createdat = $tnxs[2]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                        $this->createAndSaveTransaction($transRepo, [
                            'operationid' => $transUniqueId,
                            'transactiontype' => 'transferSenderToInviter',
                            'senderid' => $senderId,
                            'recipientid' => $recipientId,
                            'tokenamount' => $inviterAmount,
                            'transferaction' => 'INVITER_FEE',
                            'createdat' => $createdat,
                            'transactioncategory' => TransactionCategory::FEE->value
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
                        $poolFeeAmount = $tnxs[4]['numbers'];
                        $createdat = $tnxs[4]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    } elseif($withoutFeeDeduction){
                        // No fee deduction scenario
                        $senderId = $tnxs[2]['fromid'];
                        $recipientId = $tnxs[2]['userid'];
                        $poolFeeAmount = $tnxs[2]['numbers'];
                        $createdat = $tnxs[2]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    }
                    else {
                        $senderId = $tnxs[3]['fromid'];
                        $recipientId = $tnxs[3]['userid'];
                        $poolFeeAmount = $tnxs[3]['numbers'];
                        $createdat = $tnxs[3]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToPoolWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId,
                        'tokenamount' => $poolFeeAmount,
                        'transferaction' => 'POOL_FEE',
                        'transactioncategory' => TransactionCategory::FEE->value,
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
                        $peerFeeAmount = $tnxs[5]['numbers'];
                        $createdat = $tnxs[5]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    } 
                    elseif($withoutFeeDeduction){
                        // No fee deduction scenario
                        $senderId = $tnxs[3]['fromid'];
                        $recipientId = ($tnxs[3]['userid']); // Requested tokens
                        $peerFeeAmount = $tnxs[3]['numbers'];
                        $createdat = $tnxs[3]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    }
                    else {
                        $senderId = $tnxs[4]['fromid'];
                        $recipientId = ($tnxs[4]['userid']); // Requested tokens
                        $peerFeeAmount = $tnxs[4]['numbers'];
                        $createdat = $tnxs[4]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToPeerWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId,
                        'tokenamount' => $peerFeeAmount,
                        'transferaction' => 'PEER_FEE',
                        'transactioncategory' => TransactionCategory::FEE->value,
                        'createdat' => $createdat
                    ]);

                    /**
                     * 1% of requested tokens will be transferred to Burn' account
                     */
                    if ($hasInviter && isset($tnxs[6]['fromid'])) {
                        $senderId = $tnxs[6]['fromid'];
                        $recipientId = ($tnxs[6]['userid']); // Requested tokens
                        $burnFeeAmount = $tnxs[6]['numbers'];
                        $createdat = $tnxs[6]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    } 
                    elseif($withoutFeeDeduction){
                        // No fee deduction scenario
                        $senderId = $tnxs[4]['fromid'];
                        $recipientId = ($tnxs[4]['userid']); // Requested tokens
                        $burnFeeAmount = $tnxs[4]['numbers'];
                        $createdat = $tnxs[4]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                    }
                    else {
                        if (isset($tnxs[5]['fromid'])) {
                            $senderId = $tnxs[5]['fromid'];
                            $recipientId = ($tnxs[5]['userid']); // Requested tokens
                            $burnFeeAmount = $tnxs[5]['numbers'];
                            $createdat = $tnxs[5]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u');
                        }
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToBurnWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId, // burn accounts for 217+ records not found.
                        'tokenamount' => $burnFeeAmount,
                        'transferaction' => 'BURN_FEE',
                        'transactioncategory' => TransactionCategory::FEE->value,
                        'createdat' => $createdat
                    ]);

                    $this->updateLogwinStatusInBunch($txnIds, 1);

                    if(isset($saveForLogs)){
                        $this->appendLogWinMigrationRecord($saveForLogs, 'P2P_transfer_logwins_to_transaction');
                    }
                    
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

                        $this->createAndSaveTransaction($transRepo, [
                            'operationid' => $transUniqueId,
                            'transactiontype' => 'transferSenderToRecipient',
                            'transactioncategory' => TransactionCategory::P2P_TRANSFER->value,
                            'senderid' => $senderId,
                            'recipientid' => $recipientId,
                            'tokenamount' => $tnxs[1]['numbers'],
                            // 'message' => $message,
                            'transferaction' => 'CREDIT',
                            'createdat' => $tnxs[1]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u')
                        ]);

                        $saveForLogs = [
                            'operationid' => $transUniqueId,
                            'transactiontype' => 'transferSenderToRecipient',
                            'transactioncategory' => TransactionCategory::P2P_TRANSFER->value,
                            'senderid' => $senderId,
                            'recipientid' => $recipientId,
                            'tokenamount' => $tnxs[1]['numbers'],
                            // 'message' => $message,
                            'transferaction' => 'CREDIT',
                            'createdat' => $tnxs[1]['createdat'] ?? (new DateTime())->format('Y-m-d H:i:s.u')
                        ];
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
                            'transactioncategory' => TransactionCategory::FEE->value,
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
                        'transactioncategory' => TransactionCategory::FEE->value,
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
                        'transactioncategory' => TransactionCategory::FEE->value,
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
                        'transactioncategory' => TransactionCategory::FEE->value,
                        'createdat' => $createdat
                    ]);

                    $this->updateLogwinStatusInBunch($txnIds, 1);

                    if(isset($saveForLogs)){
                        $this->appendLogWinMigrationRecord($saveForLogs, 'P2P_transfer_logwins_to_transaction');
                    }


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
     * 
     */
    public function checkForUnmigratedRecords(): bool
    {

        \ignore_user_abort(true);

        ini_set('max_execution_time', '0');

        $this->logger->info('LogWinMapper.migrateTokenTransfer started');

        try {

            // Group by fromid to avoid duplicates
            $sql = "select * from logwins where migrated in (0, 2) and whereby = 18 order by createdat;";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $logwins = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($logwins)) {
                $this->logger->info('No logwins to migrate');
                return true;
            }
            $updateSql = "UPDATE logwins SET migrated = 0 WHERE migrated in (0, 2) and whereby = 18";

            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute();

            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID ' . $e->getMessage(), 41401);
        }

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


        public function saveWalletEntry(string $userId, string $liquidity, string $type = 'CREDIT'): float
    {
        \ignore_user_abort(true);
        $this->logger->debug('WalletMapper.saveWalletEntry started');

        try {
            $stmt = $this->db->prepare("SELECT liquidity FROM wallett WHERE userid = :userid FOR UPDATE");
            $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                // User does not exist, insert new wallet entry
                $newLiquidity = $liquidity;
                $liquiditq = (float)$this->decimalToQ64_96($liquidity);

                $stmt = $this->db->prepare(
                    "INSERT INTO wallett (userid, liquidity, liquiditq, updatedat)
                    VALUES (:userid, :liquidity, :liquiditq, :updatedat)"
                );
                $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                $stmt->bindValue(':liquidity', $liquidity, \PDO::PARAM_STR);
                $stmt->bindValue(':liquiditq', $liquiditq, \PDO::PARAM_STR);
                $stmt->bindValue(':updatedat', new \DateTime()->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);
                $stmt->execute();
            } else {
                // User exists, safely calculate new liquidity
                $currentBalance = (string)$row['liquidity'];

                if ($liquidity < 0) {
                    $liquidity = (string) (abs((float)$liquidity));
                    $type = 'DEBIT';
                }

                if ($type === 'CREDIT') {
                    $newLiquidity = TokenHelper::addRc($currentBalance, (string) $liquidity);
                } else {
                    $newLiquidity = TokenHelper::subRc($currentBalance, (string) $liquidity);
                }

                $liquiditq = (float)$this->decimalToQ64_96($newLiquidity);

                $stmt = $this->db->prepare(
                    "UPDATE wallett
                    SET liquidity = :liquidity, liquiditq = :liquiditq, updatedat = :updatedat
                    WHERE userid = :userid"
                );
                $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
                $stmt->bindValue(':liquidity', $newLiquidity, \PDO::PARAM_STR);
                $stmt->bindValue(':liquiditq', $liquiditq, \PDO::PARAM_STR);
                $stmt->bindValue(':updatedat', new \DateTime()->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);

                $stmt->execute();
            }

            $this->logger->info('Wallet entry saved successfully', ['newLiquidity' => $newLiquidity]);
            $this->updateUserLiquidity($userId, $newLiquidity);

            return  (float) $newLiquidity;
        } catch (\Throwable $e) {
            $this->logger->error('Database error in saveWalletEntry: ' . $e);
            throw new \RuntimeException('Unable to save wallet entry');
        }
    }



    public function updateUserLiquidity(string $userId, string $liquidity): bool
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


    private function decimalToQ64_96(string $value): string
    {
        $scaleFactor = \bcpow('2', '96');

        // Convert float to plain decimal string
        $decimalString = \number_format((float)$value, 30, '.', ''); // 30 decimal places should be enough

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

    private function appendLogWinMigrationRecord(array $payload, string $name): void
    {
        $logDir = __DIR__ . '/../../runtime-data/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . '/logwins_migration_' . $name . '.txt';
        file_put_contents(
            $logFile,
            json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }

}