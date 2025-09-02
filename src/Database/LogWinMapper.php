<?php

namespace Fawaz\Database;

use Fawaz\App\Models\Transaction;
use Fawaz\App\Repositories\TransactionRepository;
use PDO;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use Psr\Log\LoggerInterface;


class LogWinMapper
{
    use ResponseHelper;

    private string $burnWallet;

    public function __construct(protected LoggerInterface $logger, protected PDO $db, protected LiquidityPool $pool)
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
    }

    /**
     * Records Paid Actions such Post Creation, Like, Dislike, Views, Comment
     * 
     * Used for Peer Token transfer to Receipient (Peer-to-Peer transfer).  @deprecated
     * 
     * Now Peer-to-Peer transfer will be stored on `transactions` table, Refers to PeerTokenMapper->transferToken
     * 
     * Used for Actions records:
     * whereby = 1  -> Post Views
     * whereby = 2  -> Post Like
     * whereby = 3  -> Post Dislike
     * whereby = 4  -> Post Comment
     * whereby = 5  -> Post Creation
     * whereby = 18 -> Token transfer @deprecated
     * 
     * Records `Transactions` as well, for each above mentioned Actions.
     * Table `Transactions` has Foreign key on `operationsid`, which refers to `logWins`'s `token` PK. 
     */
    public function migrateLogwinData(): bool
    {
        \ignore_user_abort(true);

        $this->logger->info('LogWinMapper.migrateLogwinData started');

        try {

            $id = self::generateUUID();
            if (empty($id)) {
                $this->logger->critical('Failed to generate logwins ID');
                throw new \RuntimeException('Failed to generate logwins ID', 41401);
            }
            
            // Get 1000 Records and migrate to Transaction table
            $sql = "SELECT * FROM logwins WHERE migrated = :migrated LIMIT 1000";
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
                $userId = $value['userid'];
                $postId = $value['postid'];
                $fromId = $value['fromid'];
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
                }elseif ($value['whereby'] == 11) {
                    $transactionType = 'getPercentBeforeTransaction'; // PENDING: Should be changed according to the API Logic
                }

                /**
                * Determine the transfer type based on the number of gems.
                * If the number of gems is negative, it's a burn operation.
                * If the number of gems is positive, it's a mint operation.
                */
                if($numBers < 0){
                    $transferType = 'BURN';
                }else{
                    $transferType = 'MINT';
                }
                $this->createAndSaveTransaction($transRepo, [
                    'operationid' => $value['token'],
                    'transactiontype' => $transactionType,
                    'senderid' => $userId,
                    'recipientid' => $this->burnWallet,   
                    'tokenamount' => $numBers,
                    'transferaction' => $transferType
                ]);

                if($transferType == 'BURN'){
                    /**
                     * Add Amount to Burn account
                     *
                     * Reason behind keeping -$numBers is to, Add to Burn Account Positively
                     */
                    $this->saveWalletEntry($this->burnWallet, -$numBers);
                }

                // Update migrated status to 1
                $this->updateLogwinStatus($value['token'], 1);
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
