<?php

namespace Fawaz\Database;

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

    public function __construct(protected LoggerInterface $logger, protected PDO $db, protected LiquidityPool $pool, protected TransactionManager $transactionManager)
    {
    }

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
     * Migrate logwin data to transactions table.
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
    public function migratePaidActions(): bool
    {
        \ignore_user_abort(true);

        ini_set('max_execution_time', '0');

        $this->logger->info('LogWinMapper.migrateLogwinData started');

        try {

            // Get 1000 Records and migrate to Transaction table
            $sql = "SELECT * FROM logwins WHERE migrated = :migrated and whereby IN(1, 2, 3, 4, 5) LIMIT 100";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':migrated', 0, \PDO::PARAM_INT);
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

                try{
                    $userId = $value['userid'];
                    $postId = $value['postid'];
                    $gems = $value['gems'];
                    $numBers = $value['numbers'];
                    $createdat = $value['createdat'];

                    $this->logger->info('Migrating logwin', [
                        'userId' => $userId,
                        'postId' => $postId
                    ]);
                    
                    $transactionType = '';
                    if($value['whereby'] == 1){
                        $transactionType = 'postViewed';
                    }elseif ($value['whereby'] == 2) {
                        $transactionType = 'postLiked';
                    }elseif ($value['whereby'] == 3) {
                        $transactionType = 'postDisLiked';
                    }elseif ($value['whereby'] == 4) {
                        $transactionType = 'postComment';
                    }elseif ($value['whereby'] == 5) {
                        $transactionType = 'postCreated';
                    }

                    /**
                    * Determine the transfer type based on the number of gems.
                    * If the number of gems is negative, it's a burn operation.
                    * If the number of gems is positive, it's a mint operation.
                    */
                    $senderid = $userId;
                    if($numBers < 0){
                        $transferType = 'BURN';
                        $recipientid = $this->burnWallet; 

                    }else{
                        $transferType = 'MINT';
                        $recipientid = $userId; 
                        $senderid = $this->companyWallet; // PENDING, needs to confirms
                    }

                    /**
                     * operationid: Refers to logwin's token PK
                     */
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $value['token'],
                        'transactiontype' => $transactionType,
                        'senderid' => $senderid,
                        'recipientid' => $recipientid,
                        'tokenamount' => $numBers,
                        'transferaction' => $transferType
                    ]);

                    if($transferType == 'BURN'){
                        /**
                         * Credit to Burn Account, as till now, we didn't credit burn account
                         * 
                         * Add Amount to Burn account
                         *
                         * Reason behind keeping -$numBers is to, Add to Burn Account Positively
                         */
                        $this->saveWalletEntry($this->burnWallet, -$numBers);
                    }

                    $this->updateLogwinStatus($value['token'], 1);

                    $this->transactionManager->commit();
                }catch(\Throwable $e){
                    $this->transactionManager->rollback();
                    $this->updateLogwinStatus($value['token'], 2); // Unmigrated due to some errors
                    continue; 
                }

            }
            
            
            $this->logger->info('Inserted into logwins successfully', [
                'userId' => $userId,
                'postid' => $postId
            ]);

            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID', 41401);
        }
    }

    /**
     * Records migration for Token Transfers
     * 
     * Used for Peer Token transfer to Receipient (Peer-to-Peer transfer).  @deprecated
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
                        date_trunc('minute', lw.createdat) AS created_at_minute,
                        lw.fromid,
                        json_agg(lw ORDER BY lw.createdat) AS logwin_entries
                    FROM logwins lw
                    WHERE lw.migrated = 0
                    AND lw.whereby = 18
                    GROUP BY created_at_minute, lw.fromid
                    ORDER BY created_at_minute ASC, lw.fromid
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
                if(count($tnxs) == 7){
                    $hasInviter = true;
                }

                try{
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
                            'transferaction' => 'CREDIT'
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
                        $this->createAndSaveTransaction($transRepo, [
                            'operationid' => $transUniqueId,
                            'transactiontype' => 'transferSenderToInviter',
                            'senderid' => $senderId,
                            'recipientid' => $recipientId,
                            'tokenamount' => $amount,
                            'transferaction' => 'INVITER_FEE'
                        ]);
                    }

                    /**
                     * 1% Pool Fees will be charged when a Token Transfer happen
                     * 
                     * Credits 1% fees to Pool's Account
                     */
                    if($hasInviter && isset($tnxs[4]['fromid'])){
                        $senderId = $tnxs[4]['fromid'];
                        $recipientId = $tnxs[4]['userid'];
                        $amount = $tnxs[4]['numbers'];
                    }else{
                        $senderId = $tnxs[3]['fromid'];
                        $recipientId = $tnxs[3]['userid'];
                        $amount = $tnxs[3]['numbers'];
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToPoolWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId,
                        'tokenamount' => $amount,
                        'transferaction' => 'POOL_FEE'
                    ]);

                    /**
                     * 2% of requested tokens Peer Fees will be charged 
                     * 
                     * Credits 2% fees to Peer's Account
                     */
                    if($hasInviter && isset($tnxs[5]['fromid'])){
                        $senderId = $tnxs[5]['fromid'];
                        $recipientId = ($tnxs[5]['userid']); // Requested tokens
                        $amount = $tnxs[5]['numbers'];
                    }else{
                        $senderId = $tnxs[4]['fromid'];
                        $recipientId = ($tnxs[4]['userid']); // Requested tokens
                        $amount = $tnxs[4]['numbers'];
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToPeerWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId,
                        'tokenamount' => $amount,
                        'transferaction' => 'PEER_FEE'
                    ]);

                    /**
                     * 1% of requested tokens will be transferred to Burn' account
                     */
                    if($hasInviter && isset($tnxs[6]['fromid'])){
                        $senderId = $tnxs[6]['fromid'];
                        $recipientId = ($tnxs[6]['userid']); // Requested tokens
                        $amount = $tnxs[6]['numbers'];
                    }else{
                        if(isset($tnxs[5]['fromid'])){
                            $senderId = $tnxs[5]['fromid'];
                            $recipientId = ($tnxs[5]['userid']); // Requested tokens
                            $amount = $tnxs[5]['numbers'];

                        }
                    }
                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $transUniqueId,
                        'transactiontype' => 'transferSenderToBurnWallet',
                        'senderid' => $senderId,
                        'recipientid' => $recipientId, // burn accounts for 217+ records not found.
                        'tokenamount' => $amount,
                        'transferaction' => 'BURN_FEE'
                    ]);

                    $this->updateLogwinStatusInBunch($txnIds, 1);

                    $this->transactionManager->commit();
                }catch(\Throwable $e){
                    $this->transactionManager->rollback();
                    $this->updateLogwinStatusInBunch($txnIds, 2);

                    continue; // Skip to the next record
                }
                // Update migrated status to 1
            }
            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID ' . $e->getMessage() , 41401);
        }
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
