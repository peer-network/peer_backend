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

    private string $burnWallet;

    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db, protected LiquidityPool $pool, protected TransactionManager $transactionManager) {}

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
                    (token, userid, postid, fromid, gems, numbers, numbersq, whereby, migrated, createdat) 
                    VALUES 
                    (:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :migrated, :createdat)";


            $tokenIds = [];
            foreach ($logwins as $key => $value) {
                $this->transactionManager->beginTransaction();


                try {
                    $stmt = $this->db->prepare($sql);

                    $tokenId = self::generateUUID();

                    $numBers = '3'; // Each extra like will cost 3 Gems

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
                    $stmt->bindValue(':migrated', 1, \PDO::PARAM_INT);
                    $stmt->bindValue(':createdat', $createdat);

                    $stmt->execute();


                    $transactionType = 'postLiked';
                    $transferType = 'BURN';

                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $tokenId,
                        'transactiontype' => $transactionType,
                        'transactioncategory' => TransactionCategory::LIKE->value,
                        'senderid' => $userId,
                        'recipientid' => $this->burnWallet,
                        'tokenamount' => $numBers,
                        'transferaction' => $transferType,
                        'createdat' => $createdat
                    ]);

                    $this->saveWalletEntry($this->burnWallet, (string)$numBers, 'DEBIT');


                    $this->transactionManager->commit();
                    $tokenIds[] = $tokenId;

                } catch (\Throwable $e) {
                    $this->transactionManager->rollback();
                    $this->logger->info('Error generating logwins for Like action: ' . $e->getMessage());
                    
                    continue;
                }
            }


            return false;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to generate logwins ID'. $e->getMessage(), 41401);
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
                (token, userid, postid, fromid, gems, numbers, numbersq, whereby, migrated, createdat) 
                VALUES 
                (:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :migrated, :createdat)";

                try {
                    $stmt = $this->db->prepare($sql);

                    $tokenId = self::generateUUID();

                    $numBers = '5'; // Each extra dislike will cost 5 Gems

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
                    $stmt->bindValue(':migrated', 1, \PDO::PARAM_INT);
                    $stmt->bindValue(':createdat', $createdat);

                    $stmt->execute();


                    $transactionType = 'postDisliked';
                    $transferType = 'BURN';

                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $tokenId,
                        'transactiontype' => $transactionType,
                        'transactioncategory' => TransactionCategory::DISLIKE->value,
                        'senderid' => $userId,
                        'recipientid' => $this->burnWallet,
                        'tokenamount' => $numBers,
                        'transferaction' => $transferType,
                        'createdat' => $createdat
                    ]);

                    $this->saveWalletEntry($this->burnWallet, (string)$numBers, 'DEBIT');


                    $this->transactionManager->commit();
                    $tokenIds[] = $tokenId;
                } catch (\Throwable $e) {
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
                (token, userid, postid, fromid, gems, numbers, numbersq, whereby, migrated, createdat) 
                VALUES 
                (:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :migrated, :createdat)";

                try {
                    $stmt = $this->db->prepare($sql);

                    $tokenId = self::generateUUID();

                    $numBers = '20'; // Each extra like will cost 3 Gems

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
                    $stmt->bindValue(':migrated', 1, \PDO::PARAM_INT);
                    $stmt->bindValue(':createdat', $createdat);

                    $stmt->execute();


                    $transactionType = 'postCreated';
                    $transferType = 'BURN';

                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $tokenId,
                        'transactiontype' => $transactionType,
                        'transactioncategory' => TransactionCategory::POST_CREATE->value,
                        'senderid' => $userId,
                        'recipientid' => $this->burnWallet,
                        'tokenamount' => $numBers,
                        'transferaction' => $transferType,
                        'createdat' => $createdat
                    ]);

                    $this->saveWalletEntry($this->burnWallet, (string)$numBers, 'DEBIT');


                    $this->transactionManager->commit();
                    $tokenIds[] = $tokenId;
                } catch (\Throwable $e) {
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
                (token, userid, postid, fromid, gems, numbers, numbersq, whereby, migrated, createdat) 
                VALUES 
                (:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :migrated, :createdat)";

                try {
                    $stmt = $this->db->prepare($sql);

                    $tokenId = self::generateUUID();

                    $numBers = '0.5'; // Each extra like will cost 0.5 Gems

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
                    $stmt->bindValue(':migrated', 1, \PDO::PARAM_INT);
                    $stmt->bindValue(':createdat', $createdat);

                    $stmt->execute();


                    $transactionType = 'postComment';
                    $transferType = 'BURN';

                    $this->createAndSaveTransaction($transRepo, [
                        'operationid' => $tokenId,
                        'transactiontype' => $transactionType,
                        'transactioncategory' => TransactionCategory::COMMENT->value,
                        'senderid' => $userId,
                        'recipientid' => $this->burnWallet,
                        'tokenamount' => $numBers,
                        'transferaction' => $transferType,
                        'createdat' => $createdat
                    ]);

                    $this->saveWalletEntry($this->burnWallet, (string)$numBers, 'DEBIT');


                    $this->transactionManager->commit();
                    $tokenIds[] = $tokenId;
                } catch (\Throwable $e) {
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
            (token, userid, postid, fromid, gems, numbers, numbersq, whereby, migrated, createdat) 
            VALUES 
            (:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :migrated, :createdat)";


        $stmtLogWins = $this->db->prepare($sqlLogWins);


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
                $dailyToken =  (string) (\Fawaz\config\constants\ConstantsConfig::minting()['DAILY_NUMBER_TOKEN']);

                $gemsintoken = TokenHelper::divRc($dailyToken, $totalGems);
                $bestatigungInitial = TokenHelper::mulRc($totalGems, $gemsintoken);

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

                        $rowgems2token = TokenHelper::mulRc((string) $row['gems'],  $gemsintoken);

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
                        $gems = $args['gems'];
                        $numBers = $rowgems2token;
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
                            $stmtLogWins->bindValue(':migrated', 0, \PDO::PARAM_INT);
                            $stmtLogWins->execute();
                            $stmtLogWins->closeCursor();

                            // Create transaction entry
                            // $transactionType = match ($args['whereby']) {
                            //     1 => 'postViewed',
                            //     2 => 'postLiked',
                            //     3 => 'postDisLiked',
                            //     4 => 'postComment',
                            //     5 => 'postCreated',
                            //     default => '',
                            // };

                            // $transactionCategory = match ($args['whereby']) {
                            //     1 => TransactionCategory::TOKEN_MINT,
                            //     2 => TransactionCategory::LIKE,
                            //     3 => TransactionCategory::DISLIKE,
                            //     4 => TransactionCategory::COMMENT,
                            //     5 => TransactionCategory::POST_CREATE,
                            //     default => '',
                            // };

                            // $transferType = ($numBers < 0) ? 'BURN' : 'MINT';

                            // $senderid = $userId;
                            // if ($numBers < 0) {
                            //     $transferType = 'BURN';
                            //     $recipientid = $this->burnWallet;
                            // } else {
                            //     $transferType = 'MINT';
                            //     $recipientid = $userId;
                            //     $senderid = $this->companyWallet;
                            // }

                            // $this->createAndSaveTransaction($transRepo, [
                            //     'operationid' => $tokenId,
                            //     'transactiontype' => $transactionType,
                            //     'transactioncategory' => $transactionCategory,
                            //     'senderid' => $senderid,
                            //     'recipientid' => $recipientid,
                            //     'tokenamount' => $numBers,
                            //     'transferaction' => $transferType,
                            //     'createdat' => $createdat
                            // ]);

                            // if ($transferType === 'BURN') {
                            //     $this->saveWalletEntry($this->burnWallet, (string)$numBers, 'DEBIT');
                            // }

                            $this->logger->info('Inserted into logwins successfully', [
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

}
