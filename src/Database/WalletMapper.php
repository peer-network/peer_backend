<?php

namespace Fawaz\Database;

use Fawaz\App\Models\BtcSwapTransaction;
use Fawaz\App\Models\Transaction;
use Fawaz\App\Repositories\BtcSwapTransactionRepository;
use Fawaz\App\Repositories\TransactionRepository;
use PDO;
use Fawaz\App\Wallet;
use Fawaz\App\Wallett;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use Psr\Log\LoggerInterface;

const TABLESTOGEMS = true;
const DAILY_NUMBER_TOKEN= 5000;
const VIEW_=1;
const LIKE_=2;
const DISLIKE_=3;
const COMMENT_=4;
const POST_=5;
const INVITATION_=11;

const RECEIVELIKE=5;
const RECEIVEDISLIKE=4;
const RECEIVECOMMENT=2;
const RECEIVEPOSTVIEW=0.25;

const PRICELIKE=3;
const PRICEDISLIKE=5;
const PRICECOMMENT=0.5;
const PRICEPOST=20;

const DIRECTDEBIT_=14;
const CREDIT_=15;
const TRANSFER_=18;

const INVTFEE=0.01;
const POOLFEE=0.01;
const PEERFEE=0.02;
const BURNFEE=0.01;

class WalletMapper
{
    use ResponseHelper;
    private const DEFAULT_LIMIT = 20;
    private const MAX_WHEREBY = 100;
    private const ALLOWED_FIELDS = ['userid', 'postid', 'fromid', 'whereby'];
    private string $poolWallet;
    private string $burnWallet;
    private string $peerWallet;
    private string $btcpool;

    public function __construct(protected LoggerInterface $logger, protected PDO $db, protected LiquidityPool $pool)
    {
    }

    // Transfer Token From Wallet To Wallets
    public function transferToken(string $userId, array $args = []): ?array
    {
        \ignore_user_abort(true);

        $this->logger->info('WalletMapper.transferToken started');

		$accountsResult = $this->pool->returnAccounts();

		if (isset($accountsResult['status']) && $accountsResult['status'] === 'error') {
			$this->logger->warning('Incorrect returning Accounts', ['Error' => $accountsResult['status']]);
			return self::respondWithError(40701);
		}

		$liqpool = $accountsResult['response'] ?? null;

		if (!is_array($liqpool) || !isset($liqpool['pool'], $liqpool['peer'], $liqpool['burn'])) {
			$this->logger->warning('Fehlt Ein Von Pool, Burn, Peer Accounts', ['liqpool' => $liqpool]);
			return self::respondWithError(30102);
		}

		$this->poolWallet = $liqpool['pool'];
		$this->burnWallet = $liqpool['burn'];
		$this->peerWallet = $liqpool['peer'];

        $this->logger->info('LiquidityPool', ['liquidity' => $liqpool,]);

        $currentBalance = $this->getUserWalletBalance($userId);
        if (empty($currentBalance)) {
            $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(51301);
        }

        $recipient = (string) $args['recipient'];
        if (!self::isValidUUID($recipient)) {
            $this->logger->warning('Incorrect recipientId Exception.', [
                'recipient' => $recipient,
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(20201);
        }

        $numberoftokens = (float) $args['numberoftokens'];
        if ($numberoftokens <= 0) {
            $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                'numberoftokens' => $numberoftokens,
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(51301);
        }
        $message = isset($args['message']) ? (string) $args['message'] : null;

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
            return self::respondWithError(21001);
        }

        if ((string)$row === $userId) {
            $this->logger->warning('Send and Receive Same Wallet Error.');
            return self::respondWithError(31202);
        }

        $requiredAmount = $numberoftokens * (1 + PEERFEE + POOLFEE + BURNFEE);
        $feeAmount = round((float)$numberoftokens * POOLFEE, 2);
        $peerAmount = round((float)$numberoftokens * PEERFEE, 2);
        $burnAmount = round((float)$numberoftokens * BURNFEE, 2);
        $countAmount = $feeAmount + $peerAmount + $burnAmount;

        try {
            $query = "SELECT invited FROM users_info WHERE userid = :userid AND invited IS NOT NULL";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['userid' => $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (isset($result['invited']) && !empty($result['invited'])) {
                $inviterId = $result['invited'];
                $inviterWin = round((float)$numberoftokens * INVTFEE, 2);
                $countAmount = $feeAmount + $peerAmount + $burnAmount + $inviterWin;
                $requiredAmount = $numberoftokens * (1 + PEERFEE + POOLFEE + BURNFEE + INVTFEE);
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
            // 1. SENDER: Debit From Account
            if ($numberoftokens) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => -abs($numberoftokens),
                    'whereby' => TRANSFER_,
                ];


                $this->saveWalletEntry($userId, $args['numbers']);
                $transObj = [
                    'transUniqueId' => $transUniqueId,
                    'transactionType' => 'transferDeductSenderToRecipient',
                    'senderId' => $userId,
                    'recipientId' => $recipient,
                    'tokenAmount' => -$numberoftokens,
                    'message' => $message,
                ];
                $transactions = new Transaction($transObj);

                $transRepo = new TransactionRepository($this->logger, $this->db);
                $transRepo->saveTransaction($transactions);
            }

            // 2. RECIPIENT: Credit To Account
            if ($numberoftokens) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($numberoftokens),
                    'whereby' => TRANSFER_,
                ];

                $this->saveWalletEntry($row, $args['numbers']);

                $transUniqueIdForDebit = self::generateUUID();
                $transObj = [
                    'transUniqueId' => $transUniqueIdForDebit,
                    'transactionType' => 'transferSenderToRecipient',
                    'senderId' => $userId,
                    'recipientId' => $recipient,
                    'tokenAmount' => $numberoftokens,
                    'message' => $message,
                    'transferAction' => 'CREDIT'
                ];
                $transactions = new Transaction($transObj);

                $transRepo = new TransactionRepository($this->logger, $this->db);
                $transRepo->saveTransaction($transactions);
            }

            if (isset($result['invited']) && !empty($result['invited'])) {
                // 3 . INVITER: Fees To inviter Account (if exist)
                if ($inviterWin) {
                    $id = self::generateUUID();
                    if (empty($id)) {
                        $this->logger->critical('Failed to generate logwins ID');
                        return self::respondWithError(41401);
                    }

                    $args = [
                        'token' => $id,
                        'fromid' => $userId,
                        'numbers' => abs($inviterWin),
                        'whereby' => TRANSFER_,
                    ];

                    $this->saveWalletEntry($inviterId, $args['numbers']);
                    $transObj = [
                        'transUniqueId' => $transUniqueId,
                        'transactionType' => 'transferSenderToInviter',
                        'senderId' => $userId,
                        'recipientId' => $inviterId,
                        'tokenAmount' => $inviterWin,
                        'transferAction' => 'INVITER_FEE'
                    ];
                    $transactions = new Transaction($transObj);
    
                    $transRepo = new TransactionRepository($this->logger, $this->db);
                    $transRepo->saveTransaction($transactions);
                }
            }

            // 4. SENDER: Deduct Fees From Sender
            if ($countAmount) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => -abs($countAmount),
                    'whereby' => TRANSFER_,
                ];

                $this->saveWalletEntry($userId, $args['numbers']);

            }

            // 5. POOLWALLET: Fee To Account
            if ($feeAmount) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($feeAmount),
                    'whereby' => TRANSFER_,
                ];

                $this->saveWalletEntry($this->poolWallet, $args['numbers']);

                $transObj = [
                    'transUniqueId' => $transUniqueId,
                    'transactionType' => 'transferSenderToPoolWallet',
                    'senderId' => $userId,
                    'recipientId' => $this->poolWallet,
                    'tokenAmount' => $feeAmount,
                    'transferAction' => 'POOL_FEE'

                ];
                $transactions = new Transaction($transObj);

                $transRepo = new TransactionRepository($this->logger, $this->db);
                $transRepo->saveTransaction($transactions);
            }

            // 6. PEERWALLET: Fee To Account
            if ($peerAmount) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($peerAmount),
                    'whereby' => TRANSFER_,
                ];

                $this->saveWalletEntry($this->peerWallet, $args['numbers']);

                $transObj = [
                    'transUniqueId' => $transUniqueId,
                    'transactionType' => 'transferSenderToPeerWallet',
                    'senderId' => $userId,
                    'recipientId' => $this->peerWallet,
                    'tokenAmount' => $peerAmount,
                    'transferAction' => 'PEER_FEE'

                ];
                $transactions = new Transaction($transObj);

                $transRepo = new TransactionRepository($this->logger, $this->db);
                $transRepo->saveTransaction($transactions);
            }

            // 7. BURNWALLET: Fee Burning Tokens
            if ($burnAmount) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($burnAmount),
                    'whereby' => TRANSFER_,
                ];

                $this->saveWalletEntry($this->burnWallet, $args['numbers']);
                $transObj = [
                    'transUniqueId' => $transUniqueId,
                    'transactionType' => 'transferSenderToBurnWallet',
                    'senderId' => $userId,
                    'recipientId' => $this->burnWallet,
                    'tokenAmount' => $burnAmount,
                    'transferAction' => 'BURN_FEE'
                ];
                $transactions = new Transaction($transObj);

                $transRepo = new TransactionRepository($this->logger, $this->db);
                $transRepo->saveTransaction($transactions);
            }

            return ['status' => 'success', 'ResponseCode' => 'Successfully added to wallet.'];

        } catch (\Throwable $e) {
            return self::respondWithError($e->getMessage());
        }
    }

    public function fetchPool(array $args = []): array
    {
        $this->logger->info('WalletMapper.fetchPool started');

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = max((int)($args['limit'] ?? self::DEFAULT_LIMIT), 1);

        $conditions = ["whereby < " . self::MAX_WHEREBY];
        $queryParams = [];

        foreach ($args as $field => $value) {
            if (in_array($field, self::ALLOWED_FIELDS, true)) {
                $conditions[] = "$field = :$field";
                $queryParams[$field] = $value;
            }
        }

        $whereClause = implode(" AND ", $conditions);

        $sql = "SELECT postid, 
                       SUM(numbers) AS total_numbers, 
                       COUNT(*) AS transaction_count, 
                       (SELECT SUM(numbers) FROM wallet WHERE $whereClause) AS overall_total_numbers
                FROM wallet 
                WHERE $whereClause 
                GROUP BY postid 
                ORDER BY total_numbers DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($queryParams as $param => $value) {
            $stmt->bindValue(":$param", $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $results = [
            'overall_total_numbers' => 0,
            'overall_total_numbersq' => 0,
            'posts' => []
        ];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            try {
                if ($results['overall_total_numbers'] === 0) {
                    $results['overall_total_numbers'] = (float)($row['overall_total_numbers'] ?? 0);
                    $results['overall_total_numbersq'] = (int)$this->decimalToQ64_96($results['overall_total_numbers']);
                }

                $totalNumbers = (float)$row['total_numbers'];
                $totalNumbersQ = (int)$this->decimalToQ64_96($totalNumbers);

                $results['posts'][] = [
                    'postid' => $row['postid'],
                    'total_numbers' => $totalNumbers,
                    'total_numbersq' => $totalNumbersQ,
                    'transaction_count' => (int)$row['transaction_count'],
                ];
            } catch (\Throwable $e) {
                $this->logger->error('Failed to process row', ['error' => $e->getMessage(), 'data' => $row]);
            }
        }

        if (!empty($results['posts'])) {
            $this->logger->info('Fetched all transactions from database', ['count' => count($results['posts'])]);
        } else {
            $this->logger->warning('No transactions found in database');
        }

        return $results;
    }

    public function fetchAll(array $args = []): array
    {
        $this->logger->info('WalletMapper.fetchAll started');

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = max((int)($args['limit'] ?? 20), 1);

        $sql = "SELECT token, userid, postid, fromid, numbers, numbersq, whereby, createdat 
                FROM wallet
                WHERE whereby < 100";

        $conditions = [];
        $queryParams = [];

        foreach ($args as $field => $value) {
            if (in_array($field, ['userid', 'postid', 'fromid', 'whereby'], true)) {
                $conditions[] = "$field = :$field";
                $queryParams[":$field"] = $value;
            }
        }

        if ($conditions) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        $sql .= " ORDER BY userid, whereby LIMIT :limit OFFSET :offset";
        $queryParams[':limit'] = $limit;
        $queryParams[':offset'] = $offset;

        $this->logger->info('Executing SQL query', ['sql' => $sql, 'params' => $queryParams]);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($queryParams);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            try {
                $results[] = new Wallet($row);
                $this->logger->info('Executing SQL query', ['row' => $row]);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to create User object', ['error' => $e->getMessage(), 'data' => $row]);
            }
        }

        if ($results) {
            $this->logger->info('Fetched all transactions from database', ['count' => count($results)]);
        } else {
            $this->logger->warning('No transaction found in database');
        }

        return $results;
    }

    public function loadWalletById(string $currentUserId, ?array $args = []): array|false
    {
        $this->logger->info('WalletMapper.loadWalletById started');

        $userId = $currentUserId ?? null;
        $postId = $args['postid'] ?? null;
        $fromId = $args['fromid'] ?? null;

        if ($userId === null && $postId === null && $fromId === null) {
            $this->logger->warning('WalletMapper.loadWalletById missing required identifiers in args');
            return false;
        }

        try {
            $offset = max((int)($args['offset'] ?? 0), 0);
            $limit = max((int)($args['limit'] ?? 10), 1);

            $conditions = [];
            $params = [];

            if ($userId !== null) {
                $conditions[] = "userid = :userid";
                $params['userid'] = $userId;
            }
            if ($postId !== null) {
                $conditions[] = "postid = :postid";
                $params['postid'] = $postId;
            }
            if ($fromId !== null) {
                $conditions[] = "fromid = :fromid";
                $params['fromid'] = $fromId;
            }

            $whereClause = implode(' AND ', $conditions);

            $sql = "
                SELECT 
                    token, 
                    userid, 
                    postid, 
                    fromid, 
                    numbers, 
                    whereby, 
                    createdat 
                FROM 
                    wallet 
                WHERE 
                    $whereClause
                ORDER BY 
                    createdat DESC
                LIMIT :limit OFFSET :offset;
            ";

            $stmt = $this->db->prepare($sql);
            $params['limit'] = (int)$limit;
            $params['offset'] = (int)$offset;

            $stmt->execute($params);

            $results = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $results[] = new Wallet($row);
            }

            if ($results) {
                $this->logger->info('Fetched transactions for filters from database', [
                    'count' => count($results),
                    'filters' => $args,
                ]);
            } else {
                $this->logger->warning('No transactions found for filters in database', [
                    'filters' => $args,
                ]);
            }

            return $results;

        } catch (\Throwable $e) {
            $this->logger->error('Database error occurred in loadWalletById', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function loadLiquidityById(string $userid): float
    {
        $this->logger->info('WalletMapper.loadLiquidityById started');

        $sql = "SELECT liquidity AS currentliquidity FROM wallett WHERE userid = :userid";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['userid' => $userid]);
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($data !== false) {
            $this->logger->info('transaction found with', ['data' => $data]);
            return (float)$data['currentliquidity'];
        }

        $this->logger->warning('No transaction found with', ['userid' => $userid]);

        return 0.0;
    }

    public function insert(Wallet $wallet): Wallet
    {
        $this->logger->info('WalletMapper.insert started');

        $data = $wallet->getArrayCopy();

        $query = "INSERT INTO wallet (token, userid, postid, fromid, numbers, numbersq, whereby, createdat) 
                  VALUES (:token, :userid, :postid, :fromid, :numbers, :numbersq, :whereby, :createdat)";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind each value
            $stmt->bindValue(':token', $data['token'], \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':postid', $data['postid'], \PDO::PARAM_STR);
            $stmt->bindValue(':fromid', $data['fromid'], \PDO::PARAM_STR);
            $stmt->bindValue(':numbers', $data['numbers'], \PDO::PARAM_STR);
            $stmt->bindValue(':numbersq', $data['numbersq'], \PDO::PARAM_INT);
            $stmt->bindValue(':whereby', $data['whereby'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info('Inserted new transaction into database', ['data' => $data]);

            return new Wallet($data);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to insert transaction into database', [
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to insert wallet transaction: " . $e->getMessage());
        }
    }

    public function insertt(Wallett $wallet): Wallett
    {
        $this->logger->info('WalletMapper.insertt started');

        $data = $wallet->getArrayCopy();

        $query = "INSERT INTO wallett (userid, liquidity, liquiditq, updatedat, createdat) 
                  VALUES (:userid, :liquidity, :liquiditq, :updatedat, :createdat)";

        try {
            $stmt = $this->db->prepare($query);

            // Explicitly bind each value
            $stmt->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt->bindValue(':liquidity', $data['liquidity'], \PDO::PARAM_STR);
            $stmt->bindValue(':liquiditq', $data['liquiditq'], \PDO::PARAM_INT);
            $stmt->bindValue(':updatedat', $data['updatedat'], \PDO::PARAM_STR);
            $stmt->bindValue(':createdat', $data['createdat'], \PDO::PARAM_STR);

            $stmt->execute();

            $this->logger->info('Inserted new transaction into database', ['data' => $data]);

            return new Wallett($data);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to insert transaction into database', [
                'data' => $data,
                'exception' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to insert wallett transaction: " . $e->getMessage());
        }
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

    public function getToken(int $length = 32): string
    {
        $token = '';
        $codeAlphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        for ($i = 0; $i < $length; $i++) {
            $token .= $codeAlphabet[$this->crypto_rand_secure(0, \strlen($codeAlphabet))];
        }
        return $token;
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

    public function fetchWinsLog(string $userid, string $type, ?array $args = []): array
    {
        $this->logger->info("WalletMapper.fetchWinsLog started for type: $type");

        if (empty($userid)) {
            $this->logger->error('UserID is missing');
            return self::respondWithError(30101);
        }

        if (!in_array($type, ['win', 'pay'], true)) {
            $this->logger->error('Type is not provided');
            return self::respondWithError(30105);
        }

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit = min(max((int)($args['limit'] ?? 10), 1), 20);
        $date = $args['day'] ?? 'D0';

        $dateFilters = [
            "D0" => "lw.createdat::date = CURRENT_DATE",
            "D1" => "lw.createdat::date = CURRENT_DATE - INTERVAL '1 day'",
            "D2" => "lw.createdat::date = CURRENT_DATE - INTERVAL '2 day'",
            "D3" => "lw.createdat::date = CURRENT_DATE - INTERVAL '3 day'",
            "D4" => "lw.createdat::date = CURRENT_DATE - INTERVAL '4 day'",
            "D5" => "lw.createdat::date = CURRENT_DATE - INTERVAL '5 day'",
            "W0" => "DATE_PART('week', lw.createdat) = DATE_PART('week', CURRENT_DATE) 
                     AND EXTRACT(YEAR FROM lw.createdat) = EXTRACT(YEAR FROM CURRENT_DATE)",
            "M0" => "TO_CHAR(lw.createdat, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM')",
            "Y0" => "EXTRACT(YEAR FROM lw.createdat) = EXTRACT(YEAR FROM CURRENT_DATE)"
        ];

        $dateCondition = $dateFilters[$date] ?? $dateFilters['D0'];

        $selectColumns = "lw.postid, lw.numbers, lw.whereby AS action, lw.createdat";
        $joinCondition = "";
        $extraConditions = "";

        if ($type === 'win') {
            $selectColumns = "u.username AS from, lw.token, lw.userid, $selectColumns";
            $joinCondition = "JOIN users u ON lw.fromid = u.uid";
        } else {
            $extraConditions = "AND fromid IS NULL AND gems IS NULL";
        }

        try {
            $sql = "
                SELECT $selectColumns
                FROM logwins lw
                $joinCondition
                WHERE $dateCondition AND lw.userid = :userid $extraConditions
                ORDER BY lw.postid DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':userid', $userid, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->info("WalletMapper.fetchWinsLog rows: ", ['rows' => $rows]);
            $result = !empty($rows) ? $rows : [];

            return $result;
        } catch (\Throwable $e) {
            return self::respondWithError($e->getMessage());
        }
    }

    protected function insertWinToLog(string $userId, array $args): bool
    {
        \ignore_user_abort(true);

        $this->logger->info('WalletMapper.insertWinToLog started');

        $postId = $args['postid'] ?? null;
        $fromId = $args['fromid'] ?? null;
        $gems = $args['gems'] ?? 0.0;
        $numBers = $args['numbers'] ?? 0;
        $createdat = $args['createdat'] ?? (new \DateTime())->format('Y-m-d H:i:s.u');

        $id = self::generateUUID();
        if (empty($id)) {
            $this->logger->critical('Failed to generate logwins ID');
            return self::respondWithError(41401);
        }

        $sql = "INSERT INTO logwins 
                (token, userid, postid, fromid, gems, numbers, numbersq, whereby, createdat) 
                VALUES 
                (:token, :userid, :postid, :fromid, :gems, :numbers, :numbersq, :whereby, :createdat)";

        try {
            $stmt = $this->db->prepare($sql);

            $stmt->bindValue(':token', $args['gemid'] ?? $id, \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':postid', $postId, \PDO::PARAM_STR);
            $stmt->bindValue(':fromid', $fromId, \PDO::PARAM_STR);
            $stmt->bindValue(':gems', $gems, \PDO::PARAM_STR);
            $stmt->bindValue(':numbers', $numBers, \PDO::PARAM_STR);
            $stmt->bindValue(':numbersq', $this->decimalToQ64_96($numBers), \PDO::PARAM_STR); // 29 char precision
            $stmt->bindValue(':whereby', $args['whereby'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $createdat, \PDO::PARAM_STR);

            $stmt->execute();
            //$this->saveWalletEntry($userId, $numBers);

            $this->logger->info('Inserted into logwins successfully', [
                'userId' => $userId,
                'postid' => $postId
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to insert into logwins', [
                'userId' => $userId,
                'exception' => $e->getMessage()
            ]);

            return false;
        }
    }

    protected function insertWinToPool(string $userId, array $args): bool
    {
        \ignore_user_abort(true);

        $this->logger->info('WalletMapper.insertWinToPool started');

        $postId = $args['postid'] ?? null;
        $fromId = $args['fromid'] ?? null;
        $numBers = $args['numbers'] ?? 0;
        $createdat = $args['createdat'] ?? (new \DateTime())->format('Y-m-d H:i:s.u');

        $sql = "INSERT INTO wallet 
                (token, userid, postid, fromid, numbers, numbersq, whereby, createdat) 
                VALUES 
                (:token, :userid, :postid, :fromid, :numbers, :numbersq, :whereby, :createdat)";

        try {
            $stmt = $this->db->prepare($sql);

            $stmt->bindValue(':token', $this->getPeerToken(), \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':postid', $postId, \PDO::PARAM_STR);
            $stmt->bindValue(':fromid', $fromId, \PDO::PARAM_STR);
            $stmt->bindValue(':numbers', $numBers, \PDO::PARAM_STR);
            $stmt->bindValue(':numbersq', $this->decimalToQ64_96($numBers), \PDO::PARAM_STR); // 29 char precision
            $stmt->bindValue(':whereby', $args['whereby'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $createdat, \PDO::PARAM_STR);

            $stmt->execute();

            $this->saveWalletEntry($userId, $numBers);

            $this->logger->info('Inserted into wallet successfully', [
                'userId' => $userId,
                'postid' => $postId
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to insert into wallet', [
                'userId' => $userId,
                'exception' => $e->getMessage()
            ]);

            return false;
        }
    }

    public function callGlobalWins(): array
    {
        if (!TABLESTOGEMS) {
            return self::respondWithError(41215);
        }

        $wins = [
            ['table' => 'user_post_views', 'winType' => (int)VIEW_, 'factor' => (float)RECEIVEPOSTVIEW],
            ['table' => 'user_post_likes', 'winType' => (int)LIKE_, 'factor' => (float)RECEIVELIKE],
            ['table' => 'user_post_dislikes', 'winType' => (int)DISLIKE_, 'factor' => -(float)RECEIVEDISLIKE],
            ['table' => 'user_post_comments', 'winType' => (int)COMMENT_, 'factor' => (float)RECEIVECOMMENT]
        ];

        $totalInserts = 0;
        $winSources = []; 

        foreach ($wins as $win) {
            $result = $this->setGlobalWins($win['table'], $win['winType'], $win['factor']);
            
            if ($result['status'] === 'error') {
                $this->logger->error("Failed to set global wins for {$win['table']}");
            }

            if (isset($result['insertCount']) && $result['insertCount'] > 0) {
                $totalInserts += $result['insertCount'];

                $tablePart = strtolower(substr($win['table'], strrpos($win['table'], '_') + 1));
                $winSources[] = $tablePart;
            }
        }

        if ($totalInserts > 0) {
            $sourceList = implode(', ', $winSources);
            $success = ['status' => 'success', 'ResponseCode' => 11206];
            return $success;
        }

        $success = ['status' => 'success', 'ResponseCode' => 21205];
        return $success;
    }

    protected function setGlobalWins(string $tableName, int $winType, float $factor): array
    {
        \ignore_user_abort(true);

        $this->logger->info('WalletMapper.setGlobalWins started');

        try {
            $sql = "SELECT s.userid, s.postid, s.createdat, p.userid as poster 
                    FROM $tableName s 
                    INNER JOIN posts p ON s.postid = p.postid AND s.userid != p.userid 
                    WHERE s.collected = 0";
            $stmt = $this->db->query($sql);
            $entries = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching entries for ' . $tableName, ['exception' => $e]);
            return self::respondWithError(41208);
        }

        $insertCount = 0; 

        if (!empty($entries)) {
            $entry_ids = array_map(fn($row) => isset($row['userid']) && is_string($row['userid']) ? $this->db->quote($row['userid']) : null, $entries);
            $entry_ids = array_filter($entry_ids);

            foreach ($entries as $row) {
                try {
                    $id = self::generateUUID();
                    if (empty($id)) {
                        $this->logger->critical('Failed to generate gems ID');
                        return self::respondWithError(41401);
                    }

                    $sql = "INSERT INTO gems (gemid, userid, postid, fromid, gems, whereby, createdat) 
                            VALUES (:gemid, :userid, :postid, :fromid, :gems, :whereby, :createdat)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        ':gemid' => $id,
                        ':userid' => $row['poster'],
                        ':postid' => $row['postid'],
                        ':fromid' => $row['userid'],
                        ':gems' => $factor,
                        ':whereby' => $winType,
                        ':createdat' => $row['createdat']
                    ]);

                    $insertCount++;
                } catch (\Throwable $e) {
                    $this->logger->error('Error inserting into gems for ' . $tableName, ['exception' => $e]);
                    return self::respondWithError(41210);
                }
            }

            if (!empty($entry_ids)) {
                try {
                    $quoted_ids = implode(',', $entry_ids);
                    $sql = "UPDATE $tableName SET collected = 1 WHERE userid IN ($quoted_ids)";
                    $this->db->query($sql);
                } catch (\Throwable $e) {
                    $this->logger->error('Error updating collected status for ' . $tableName, ['exception' => $e]);
                    return self::respondWithError(41211);
                }
            }
        }

        return [
            'status' => 'success',
            'insertCount' => $insertCount
        ];
    }

    public function getTimeSorted()
    {
        try {

            $sql = "
                SELECT 
                COUNT(CASE WHEN createdat::date = CURRENT_DATE THEN 1 END) AS d0,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '1 day' THEN 1 END) AS d1,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '2 day' THEN 1 END) AS d2,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '3 day' THEN 1 END) AS d3,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '4 day' THEN 1 END) AS d4,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '5 day' THEN 1 END) AS d5,
                COUNT(CASE WHEN DATE_PART('week', createdat) = DATE_PART('week', CURRENT_DATE) AND EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE) THEN 1 END) AS w0,
                COUNT(CASE WHEN TO_CHAR(createdat, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM') THEN 1 END) AS m0,
                COUNT(CASE WHEN EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE) THEN 1 END) AS y0
                FROM gems WHERE collected = 0
            ";
            
            $stmt = $this->db->query($sql);
            $entries = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->logger->info('fetching entries for ', ['entries' => $entries]);
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching entries for ', ['exception' => $e->getMessage()]);
            return self::respondWithError(41208);
        }

        $success = [
            'status' => 'success',
            'ResponseCode' => 11207,
            'affectedRows' => $entries
        ];

        return $success;

    }

    public function getTimeSortedMatch(string $day = 'D0'): array
    {
        \ignore_user_abort(true);

        $this->logger->info('WalletMapper.getTimeSortedMatch started');

        $dayOptionsRaw = [
            "D0" => "createdat::date = CURRENT_DATE",
            "D1" => "createdat::date = CURRENT_DATE - INTERVAL '1 day'",
            "D2" => "createdat::date = CURRENT_DATE - INTERVAL '2 day'",
            "D3" => "createdat::date = CURRENT_DATE - INTERVAL '3 day'",
            "D4" => "createdat::date = CURRENT_DATE - INTERVAL '4 day'",
            "D5" => "createdat::date = CURRENT_DATE - INTERVAL '5 day'",
            "W0" => "DATE_PART('week', createdat) = DATE_PART('week', CURRENT_DATE) AND EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE)",
            "M0" => "TO_CHAR(createdat, 'YYYY-MM') = TO_CHAR(CURRENT_DATE, 'YYYY-MM')",
            "Y0" => "EXTRACT(YEAR FROM createdat) = EXTRACT(YEAR FROM CURRENT_DATE)"
        ];

        if (!array_key_exists($day, $dayOptionsRaw)) {
            return self::respondWithError(30105);
        }

        $whereConditionRaw = $dayOptionsRaw[$day];

        $whereConditionAliased = preg_replace('/\b(createdat)\b/', 'g.$1', $whereConditionRaw);

        $sql = "
            WITH user_sums AS (
                SELECT 
                    userid,
                    GREATEST(SUM(gems), 0) AS total_numbers
                FROM gems
                WHERE {$whereConditionRaw} AND collected = 0
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
            WHERE us.total_numbers > 0 AND g.collected = 0 AND {$whereConditionAliased};
        ";

        try {
            $stmt = $this->db->query($sql);
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            //$this->logger->info('fetching data for ', ['data' => $data]);
        } catch (\Throwable $e) {
            $this->logger->error('Error reading gems', ['exception' => $e->getMessage()]);
            return self::respondWithError(41209);
        }

        if (empty($data)) {
            return self::respondWithError(21206);
        }

        $totalGems = isset($data[0]['overall_total']) ? (string)$data[0]['overall_total'] : '0';
        $dailyToken = DAILY_NUMBER_TOKEN;

        $gemsintoken = bcdiv("$dailyToken", "$totalGems", 10);

        $bestatigung = bcadd(bcmul($totalGems, $gemsintoken, 10), '0.00005', 4);

        $args = [
            'winstatus' => [
                'totalGems' => $totalGems,
                'gemsintoken' => $gemsintoken,
                'bestatigung' => $bestatigung
            ]
        ];

        foreach ($data as $row) {
            $userId = (string)$row['userid'];

            if (!isset($args[$userId])) {
                $args[$userId] = [
                    'userid' => $userId,
                    'gems' => $row['total_numbers'],
                    'tokens' => bcmul($row['total_numbers'], $gemsintoken, 10),
                    'percentage' => $row['percentage'],
                    'details' => []
                ];
            }

            $rowgems2token = bcmul($row['gems'], $gemsintoken, 10);

            $args[$userId]['details'][] = [
                'gemid' => $row['gemid'],
                'userid' => $row['userid'],
                'postid' => $row['postid'],
                'fromid' => $row['fromid'],
                'gems' => $row['gems'],
                'numbers' => $rowgems2token,
                'whereby' => $row['whereby'],
                'createdat' => $row['createdat']
            ];

            // Also needs to add records in log_gems in FUTURE
            $transUniqueId = self::generateUUID();
            $this->saveWalletEntry($userId, $rowgems2token);
            $transObj = [
                'transUniqueId' => $transUniqueId,
                'transactionType' => 'mint',
                'senderId' => $userId,
                'recipientId' => null,
                'tokenAmount' => $rowgems2token,
                'message' => 'Airdrop',
            ];
            $transactions = new Transaction($transObj);
            $transRepo = new TransactionRepository($this->logger, $this->db);
            $transRepo->saveTransaction($transactions);

            
            // $this->insertWinToLog($userId, end($args[$userId]['details']));
            // $this->insertWinToPool($userId, end($args[$userId]['details']));
        }

        if (!empty($data)) {
            try {
                $gemIds = array_column($data, 'gemid');
                $quotedGemIds = array_map(fn($gemId) => $this->db->quote($gemId), $gemIds);

                $this->db->query('UPDATE gems SET collected = 1 WHERE gemid IN (' . \implode(',', $quotedGemIds) . ')');

            } catch (\Throwable $e) {
                $this->logger->error('Error updating gems or liquidity', ['exception' => $e->getMessage()]);
                return self::respondWithError(41212);
            }

            return [
                'status' => 'success',
                'counter' => count($args) -1,
                'ResponseCode' => 11208,
                'affectedRows' => ['data' => array_values($args), 'totalGems' => $totalGems]
            ];
        }
    }

    public function getPercentBeforeTransaction(string $userId, int $tokenAmount) : array
    {
        $this->logger->info('WalletMapper.getPercentBeforeTransaction started');

        $account = $this->getUserWalletBalance($userId);
        $createdat = (new \DateTime())->format('Y-m-d H:i:s.u');

        if ($account <= $tokenAmount) {
            $this->logger->warning('Insufficient funds for the transaction', ['userid' => $userId, 'accountBalance' => $account, 'tokenAmount' => $tokenAmount]);
            return self::respondWithError(51401);
        }

        try {
            $query = "SELECT invited FROM users_info WHERE userid = :userid AND invited IS NOT NULL";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['userid' => $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$result || !$result['invited']) {
                $this->logger->warning('No inviter found for the given user', ['userid' => $userId]);
                return self::respondWithError(11401);
            }

            $inviterId = $result['invited'];
            $this->logger->info('Inviter found', ['inviterId' => $inviterId]);

            $percent = round((float)$tokenAmount * INVTFEE, 2);
            $tosend = round((float)$tokenAmount - $percent, 2);

            if ($result) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'postid' => null,
                    'fromid' => $inviterId,
                    'numbers' => -abs($tokenAmount),
                    'whereby' => INVITATION_,
                    'createdat' => $createdat,
                ];

                // TRANSACTION TABLE
                $this->insertWinToLog($userId, $args);
            }

            if ($result) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'postid' => null,
                    'fromid' => $userId,
                    'numbers' => abs($percent),
                    'whereby' => INVITATION_,
                    'createdat' => $createdat,
                ];

                // TRANSACTION TABLE
                $this->insertWinToLog($inviterId, $args);
            }

            return [
                'status' => 'success', 
                'ResponseCode' => 11402,
                'affectedRows' => [
                    'inviterId' => $inviterId,
                    'tosend' => $tosend,
                    'percentTransferred' => $percent
                ]
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Throwable occurred during transaction', ['exception' => $e]);
            return self::respondWithError(41401);
        }

        return self::respondWithError(41401);
    }

    public function deductFromWallets(string $userId, ?array $args = []): array
    {
        $this->logger->info('WalletMapper.deductFromWallets started');
        $this->logger->info('deductFromWallets commenrs args.', ['args' => $args]);

        $postId = $args['postid'] ?? null;
        $art = $args['art'] ?? null;
        $fromId = $args['fromid'] ?? null;

        $mapping = [
            2 => ['price' => PRICELIKE, 'whereby' => LIKE_, 'text' => 'Buy like'],
            3 => ['price' => PRICEDISLIKE, 'whereby' => DISLIKE_, 'text' => 'Buy dislike'],
            4 => ['price' => PRICECOMMENT, 'whereby' => COMMENT_, 'text' => 'Buy comment'],
            5 => ['price' => PRICEPOST, 'whereby' => POST_, 'text' => 'Buy post'],
        ];

        if (!isset($mapping[$art])) {
            $this->logger->error('Invalid art type provided.', ['art' => $art]);
            return self::respondWithError(30105);
        }

        $price = $mapping[$art]['price'];
        $whereby = $mapping[$art]['whereby'];
        $text = $mapping[$art]['text'];

        $currentBalance = $this->getUserWalletBalance($userId);

        if ($currentBalance < $price) {
            $this->logger->warning('Insufficient balance.', [
                'userId' => $userId,
                'Balance' => $currentBalance,
                'requiredAmount' => $price,
            ]);
            return self::respondWithError(51301);
        }

        $args = [
            'postid' => $postId,
            'fromid' => $fromId,
            'gems' => 0.0,
            'numbers' => -abs($price),
            'whereby' => $whereby,
            'createdat' => (new \DateTime())->format('Y-m-d H:i:s.u'),
        ];

        try {
            // TRANSACTION TABLE: MAY BE
            $results = $this->insertWinToLog($userId, $args);
            if ($results === false) {
                return self::respondWithError(41206);
            }

            // TRANSACTION TABLE: MAY BE
            $results = $this->insertWinToPool($userId, $args);
            if ($results === false) {
                return self::respondWithError(41206);
            }

            $this->logger->info('Wallet deduction successful.', [
                'userId' => $userId,
                'postId' => $postId,
                'gems' => 0.0,
                'numbers' => -abs($price),
                'whereby' => $whereby,
            ]);

            return [
                'status' => 'success',
                'ResponseCode' => 11209,
                'affectedRows' => [
                    'userId' => $userId,
                    'postId' => $postId,
                    'numbers' => -abs($price),
                    'whereby' => $whereby,
                ],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Failed to deduct from wallet.', [
                'exception' => $e->getMessage(),
                'params' => [
                    'userId' => $userId,
                    'postId' => $postId,
                    'numbers' => -abs($price),
                    'whereby' => $whereby,
                ],
            ]);
            return self::respondWithError(41206);
        }
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

    public function updateUserLiquidity(string $userId, float $liquidity): bool
    {
        try {
            $totalNumbers = $liquidity ?? 0;

            $sqlUpdate = "UPDATE users_info SET liquidity = :liquidity, updatedat = CURRENT_TIMESTAMP WHERE userid = :userid";
            $stmt = $this->db->prepare($sqlUpdate);

            $stmt->bindValue(':liquidity', $totalNumbers, \PDO::PARAM_STR);
            $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);

            $stmt->execute();

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Error updating liquidity', ['exception' => $e->getMessage(), 'userid' => $userId]);
            return false;
        }
    }

    public function saveWalletEntry(string $userId, float $liquidity): float
    {
        \ignore_user_abort(true);
        $this->logger->info('WalletMapper.saveWalletEntry started');

        try {
            $this->db->beginTransaction();

            $query = "SELECT 1 FROM wallett WHERE userid = :userid";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':userid', $userId, \PDO::PARAM_STR);
            $stmt->execute();
            $userExists = $stmt->fetchColumn(); 

            if (!$userExists) {
                $newLiquidity = abs($liquidity);
                $liquiditq = abs($this->decimalToQ64_96($newLiquidity));

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
                $newLiquidity = abs($currentBalance + $liquidity);
                $liquiditq = abs($this->decimalToQ64_96($newLiquidity));

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
            $this->updateUserLiquidity($userId, $newLiquidity);

            return $newLiquidity;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error('Database error in saveWalletEntry: ' . $e->getMessage());
            throw new \RuntimeException('Unable to save wallet entry');
        }
    }

    public function callUserMove(string $userId): array
    {
        try {
            $wins = [
                ['table' => 'user_post_views', 'winType' => (int)VIEW_, 'factor' => (float)RECEIVEPOSTVIEW, 'key' => 'views'],
                ['table' => 'user_post_likes', 'winType' => (int)LIKE_, 'factor' => (float)RECEIVELIKE, 'key' => 'likes'],
                ['table' => 'user_post_dislikes', 'winType' => (int)DISLIKE_, 'factor' => -(float)RECEIVEDISLIKE, 'key' => 'dislikes'],
                ['table' => 'user_post_comments', 'winType' => (int)COMMENT_, 'factor' => (float)RECEIVECOMMENT, 'key' => 'comments']
            ];

            $totalInteractions = 0;
            $totalScore = 0.0;
            $winSources = [];
            $interactionDetails = [
                'views' => 0,
                'likes' => 0,
                'dislikes' => 0,
                'comments' => 0,
                'viewsScore' => 0.0,
                'likesScore' => 0.0,
                'dislikesScore' => 0.0,
                'commentsScore' => 0.0
            ];

            foreach ($wins as $win) {
                $result = $this->GetUserMove($win['table'], $win['winType'], $win['factor'], $userId);

                if ($result['status'] === 'error') {
                    $this->logger->error("Failed to process {$win['table']}");
                    continue;
                }

                if (!empty($result['insertCount'])) {
                    $totalInteractions += $result['insertCount'];
                    $totalScore += $result['totalFactor'];
                    $winSources[] = strtolower($win['key']);

                    $interactionDetails[$win['key']] = $result['insertCount'];
                    $interactionDetails[$win['key'] . 'Score'] = $result['totalFactor'];
                }
            }

            return [
                'status' => 'success',
                'ResponseCode' => $totalInteractions > 0
                    ? 11205
                    : 21204,
                'affectedRows' => array_merge(['totalInteractions' => $totalInteractions, 'totalScore' => $totalScore, 'totalDetails' => $interactionDetails])
            ];
        } catch (\Throwable $e) {
            $this->logger->error('An error occurred while processing user move', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'ResponseCode' => 41205,
                'affectedRows' => []
            ];
        }
    }

    protected function GetUserMove(string $tableName, int $winType, float $factor, string $userId): array
    {
        \ignore_user_abort(true);
        $this->logger->info("Fetching interactions for user $userId from $tableName");

        try {
            $sql = "
                SELECT COUNT(*) as interaction_count
                FROM $tableName s
                INNER JOIN posts p ON s.postid = p.postid
                WHERE p.userid = :userId 
                  AND s.createdat >= CURRENT_DATE
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['userId' => $userId]);

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $interactionCount = (int)($result['interaction_count'] ?? 0);

            $totalFactor = $interactionCount * $factor;

        } catch (\PDOException $e) {
            $this->logger->error("Database error fetching entries for $tableName", [
                'userId' => $userId,
                'tableName' => $tableName,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => "Database error: " . $e->getMessage()
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Unexpected error fetching entries for $tableName", [
                'userId' => $userId,
                'tableName' => $tableName,
                'error' => $e->getMessage()
            ]);

            return [
                'status' => 'error',
                'message' => "Unexpected error: " . $e->getMessage()
            ];
        }

        return [
            'status' => 'success',
            'insertCount' => $interactionCount,
            'totalFactor' => $totalFactor
        ];
    }

    private function decimalToQ64_96(float $value): string
    {
        $scaleFactor = bcpow('2', '96');
        
        $scaledValue = bcmul((string)$value, $scaleFactor, 0);
        
        return $scaledValue;
    }

    private function q64_96ToDecimal(string $qValue): string
    {
        $scaleFactor = bcpow('2', '96');
        
        $decimalValue = bcdiv($qValue, $scaleFactor, 18);
        
        return round($decimalValue, 2);
    }

    private function addQ64_96(string $qValue1, string $qValue2): string
    {
        return bcadd($qValue1, $qValue2);
    }


    /**
     * get transcation history of current user.
     * 
     * @param userId string
     * @param offset int
     * @param limit int
     * 
     */
    public function getLiquidityPoolHistory(string $userId, int $offset, int $limit): ?array
    {
        $this->logger->info('Fetching transaction history - WalletMapper.getLiquidityPoolHistory', ['userId' => $userId]);

        $query = "
                    SELECT 
                        *
                    FROM transactions AS tt
                    LEFT JOIN btc_swap_transactions AS bt ON tt.transactionid = bt.transuniqueid
                    WHERE 
                        tt.senderid = :senderid AND tt.transactiontype = :transactiontype
                    ORDER BY tt.createdat DESC
                    LIMIT :limit OFFSET :offset
                ";
    
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':senderid', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':transactiontype', "btcSwap", \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
        
            $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC); 
        
            return [
                'status' => 'success',
                'ResponseCode' => 0000,
                'affectedRows' => $transactions
            ];
        } catch (\PDOException $e) {
            $this->logger->error("Database error while fetching transactions - WalletMapper.getLiquidityPoolHistory", ['error' => $e->getMessage()]);
            
        }
        return [
            'status' => 'error',
            'ResponseCode' => 0000,
            'affectedRows' => []
        ];
    }

    
    /**
     * update transcation status to PAID.
     * 
     * @param userId string
     * @param offset int
     * @param limit int
     * 
     */
    public function updateSwapTranStatus(string $swapId): ?array
    {
        \ignore_user_abort(true);
        $this->logger->info('WalletMapper.updateSwapTranStatus started');

        try {
            $this->db->beginTransaction();

            $query = "SELECT 1 FROM btc_swap_transactions WHERE swapid = :swapid AND status = :status";
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':status', "PENDING", \PDO::PARAM_STR);
            $stmt->bindValue(':swapid', $swapId, \PDO::PARAM_STR);
            $stmt->execute();
            $transExists = $stmt->fetchColumn(); 

            if ($transExists) {
                $query = "UPDATE btc_swap_transactions
                          SET status = :status
                          WHERE swapid = :swapid";
                $stmt = $this->db->prepare($query);
                $stmt->bindValue(':swapid', $swapId, \PDO::PARAM_STR);
                $stmt->bindValue(':status', 'PAID', \PDO::PARAM_STR);
                $stmt->execute();
            }
            $this->db->commit();

            return [
                'status' => 'success',
                'ResponseCode' => 0000,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error('Database error in saveWalletEntry: ' . $e->getMessage());
            throw new \RuntimeException('Unable to save wallet entry');
        }
    
        return [
            'status' => 'error',
            'ResponseCode' => 0000
        ];
    }
    
    /**
     * get swap transcation history of current user.
     * 
     * @param userId string
     * @param offset int
     * @param limit int
     * 
     */
    public function transcationsHistory(string $userId, int $offset, int $limit): ?array
    {

        $this->logger->info('Fetching transaction history - WalletMapper.transactionsHistory', ['userId' => $userId]);

        $query = "
                    SELECT 
                        *
                    FROM transactions
                    WHERE 
                        senderid = :senderid 
                        AND transferaction != 'CREDIT'
                    ORDER BY createdat DESC
                    LIMIT :limit OFFSET :offset
                ";
    
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':senderid', $userId, \PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
        
            $transactions = $stmt->fetchAll(\PDO::FETCH_ASSOC); 
        
            return [
                'status' => 'success',
                'ResponseCode' => 0000,
                'affectedRows' => $transactions
            ];
        } catch (\PDOException $e) {
            $this->logger->error("Database error while fetching transactions - WalletMapper.transactionsHistory", ['error' => $e->getMessage()]);
            
        }
        return [
            'status' => 'error',
            'ResponseCode' => 0000,
            'affectedRows' => []
        ];
    }


    /**
     * get token price
     * 
     * @param userId string
     * @param offset int
     * @param limit int
     * 
     */
    public function getTokenPrice(): ?array
    {

        $this->logger->info('WalletMapper.getTokenPrice');

        try {
            
            $getLpToken = $this->getLpInfo();
            $getLpTokenBtcLP = $this->getLpTokenBtcLP();

            $tokenPrice =  (float)  $getLpTokenBtcLP / (float) ($getLpToken['liquidity']);

            return [
                'status' => 'success',
                'ResponseCode' => 0000,
                'currentTokenPrice' => $tokenPrice,
                'updatedAt' => $getLpToken['updatedat'],

            ];
        } catch (\PDOException $e) {
            $this->logger->error("Database error while fetching transactions - WalletMapper.transactionsHistory", ['error' => $e->getMessage()]);
        }
        return [
            'status' => 'error',
            'ResponseCode' => 0000,
        ];
    }

    /**
     * get transcation history of current user.
     * 
     * @param userId string
     * @param args array
     * 
     */    
    public function swapTokens(string $userId, array $args = []): ?array
    {
        \ignore_user_abort(true);

        $this->logger->info('WalletMapper.swapTokens started');

        if (empty($args['btcAddress'])) {
            $this->logger->warning('BTC Address required');
            // Please create New Response code message for "BTC Address is required!"
            return self::respondWithError(0000);
        }

        $this->initializeLiquidityPool();

        $currentBalance = $this->getUserWalletBalance($userId);
        if (empty($currentBalance)) {
            $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(51301);
        }

        $recipient = (string) $this->poolWallet;

        $numberoftokens = (float) $args['numberoftokens'];
       
        if ($numberoftokens <= 0) {
            $this->logger->warning('Incorrect Amount Exception: Insufficient balance', [
                'numberoftokens' => $numberoftokens,
                'Balance' => $currentBalance,
            ]);
            return self::respondWithError(51301);
        }
        $message = isset($args['message']) ? (string) $args['message'] : null;

        $requiredAmount = $numberoftokens * (1 + PEERFEE + POOLFEE + BURNFEE);
        $feeAmount = round((float)$numberoftokens * POOLFEE, 2);
        $peerAmount = round((float)$numberoftokens * PEERFEE, 2);
        $burnAmount = round((float)$numberoftokens * BURNFEE, 2);
        $countAmount = $feeAmount + $peerAmount + $burnAmount;

        try {
            $query = "SELECT invited FROM users_info WHERE userid = :userid AND invited IS NOT NULL";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['userid' => $userId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (isset($result['invited']) && !empty($result['invited'])) {
                $inviterId = $result['invited'];
                $inviterWin = round((float)$numberoftokens * INVTFEE, 2);
                $countAmount = $feeAmount + $peerAmount + $burnAmount + $inviterWin;
                $requiredAmount = $numberoftokens * (1 + PEERFEE + POOLFEE + BURNFEE + INVTFEE);
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

            $btcAddress = $args['btcAddress'];
            $transUniqueId = self::generateUUID();

            $btcConstInitialY =  $this->getLpTokenBtcLP();
            
            // 1. SENDER: Debit From Account
            if ($numberoftokens) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => -abs($numberoftokens),
                    'whereby' => TRANSFER_,
                ];


                $this->saveWalletEntry($userId, $args['numbers']);
                $transactionId = self::generateUUID();

                $transObj = [
                    'transactionId' => $transactionId,
                    'transUniqueId' => $transUniqueId,
                    'transactionType' => 'btcSwap',
                    'senderId' => $userId,
                    'recipientId' => $recipient,
                    'tokenAmount' => -$numberoftokens,
                    'message' => $message,
                ];
                $transactions = new Transaction($transObj);

                $transRepo = new TransactionRepository($this->logger, $this->db);
                $transRepo->saveTransaction($transactions);

            }


            if (isset($result['invited']) && !empty($result['invited'])) {
                // 3 . INVITER: Fees To inviter Account (if exist)
                if ($inviterWin) {
                    $id = self::generateUUID();
                    if (empty($id)) {
                        $this->logger->critical('Failed to generate logwins ID');
                        return self::respondWithError(41401);
                    }

                    $args = [
                        'token' => $id,
                        'fromid' => $userId,
                        'numbers' => abs($inviterWin),
                        'whereby' => TRANSFER_,
                    ];

                    $this->saveWalletEntry($inviterId, $args['numbers']);
                    $transObj = [
                        'transUniqueId' => $transUniqueId,
                        'transactionType' => 'transferSenderToInviter',
                        'senderId' => $userId,
                        'recipientId' => $inviterId,
                        'tokenAmount' => $inviterWin,
                        'transferAction' => 'INVITER_FEE'
                    ];
                    $transactions = new Transaction($transObj);
    
                    $transRepo = new TransactionRepository($this->logger, $this->db);
                    $transRepo->saveTransaction($transactions);
                }
            }

            // 4. SENDER: Deduct Fees From Sender
            if ($countAmount) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => -abs($countAmount),
                    'whereby' => TRANSFER_,
                ];

                $this->saveWalletEntry($userId, $args['numbers']);
            }

            // 5. POOLWALLET: Fee To Account
            if ($feeAmount) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($feeAmount),
                    'whereby' => TRANSFER_,
                ];

                $this->saveWalletEntry($this->poolWallet, $args['numbers']);

                $transObj = [
                    'transUniqueId' => $transUniqueId,
                    'transactionType' => 'transferSenderToPoolWallet',
                    'senderId' => $userId,
                    'recipientId' => $this->poolWallet,
                    'tokenAmount' => $feeAmount,
                    'transferAction' => 'POOL_FEE'

                ];
                $transactions = new Transaction($transObj);

                $transRepo = new TransactionRepository($this->logger, $this->db);
                $transRepo->saveTransaction($transactions);


                // Count LP after Fees calculation
                $lpAccountTokenAfterLPFeeX = $this->getLpToken();
                $contsAfterFeesK = $lpAccountTokenAfterLPFeeX * $btcConstInitialY;
            }

            // 2. RECIPIENT: Credit To Account to Pool Account
            if ($numberoftokens) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($numberoftokens),
                    'whereby' => TRANSFER_,
                ];

                $this->saveWalletEntry($recipient, $args['numbers']);

                $transUniqueIdForDebit = self::generateUUID();
                $transObj = [
                    'transUniqueId' => $transUniqueIdForDebit,
                    'transactionType' => 'btcSwapToPool',
                    'senderId' => $userId,
                    'recipientId' => $recipient,
                    'tokenAmount' => $numberoftokens,
                    'message' => $message,
                    'transferAction' => 'CREDIT'
                ];
                $transactions = new Transaction($transObj);

                $transRepo = new TransactionRepository($this->logger, $this->db);
                $transRepo->saveTransaction($transactions);


                // Count LP swap tokens Fees calculation
                $lpAccountTokenAfterSwapX = $this->getLpToken();
                $btcConstNewY = $contsAfterFeesK / $lpAccountTokenAfterSwapX;

            }

            // 6. PEERWALLET: Fee To Account
            if ($peerAmount) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($peerAmount),
                    'whereby' => TRANSFER_,
                ];

                $this->saveWalletEntry($this->peerWallet, $args['numbers']);

                $transObj = [
                    'transUniqueId' => $transUniqueId,
                    'transactionType' => 'transferSenderToPeerWallet',
                    'senderId' => $userId,
                    'recipientId' => $this->peerWallet,
                    'tokenAmount' => $peerAmount,
                    'transferAction' => 'PEER_FEE'

                ];
                $transactions = new Transaction($transObj);

                $transRepo = new TransactionRepository($this->logger, $this->db);
                $transRepo->saveTransaction($transactions);
            }

            // 7. BURNWALLET: Fee Burning Tokens
            if ($burnAmount) {
                $id = self::generateUUID();
                if (empty($id)) {
                    $this->logger->critical('Failed to generate logwins ID');
                    return self::respondWithError(41401);
                }

                $args = [
                    'token' => $id,
                    'fromid' => $userId,
                    'numbers' => abs($burnAmount),
                    'whereby' => TRANSFER_,
                ];

                $this->saveWalletEntry($this->burnWallet, $args['numbers']);
                $transObj = [
                    'transUniqueId' => $transUniqueId,
                    'transactionType' => 'transferSenderToBurnWallet',
                    'senderId' => $userId,
                    'recipientId' => $this->burnWallet,
                    'tokenAmount' => $burnAmount,
                    'transferAction' => 'BURN_FEE'
                ];
                $transactions = new Transaction($transObj);

                $transRepo = new TransactionRepository($this->logger, $this->db);
                $transRepo->saveTransaction($transactions);
            }

            // Should be placed at last because it should include 1% LP Fees
            if($numberoftokens && $transactionId){
                // Store BTC Swap transactions in btc_swap_transactions
                // count BTC amount
                $btcAmountToUser = $btcConstInitialY - $btcConstNewY;
                $transObj = [
                    'transUniqueId' => $transactionId,
                    'transactionType' => 'btcSwapToPool',
                    'userId' => $userId,
                    'btcAddress' => $btcAddress,
                    'tokenAmount' => $numberoftokens,
                    'btcAmount' => $btcAmountToUser,
                    'message' => $message,
                    'transferAction' => 'CREDIT'
                ];
                $btcTransactions = new BtcSwapTransaction($transObj);

                $btcTransRepo = new BtcSwapTransactionRepository($this->logger, $this->db);
                $btcTransRepo->saveTransaction($btcTransactions);
            }

            // Update BTC Pool
            if($btcAmountToUser){
                $btcAmountToUpdateInBtcPool = -abs($btcAmountToUser);
                $this->saveWalletEntry($this->btcpool, $btcAmountToUpdateInBtcPool);
            }

            return [
                'status' => 'success', 
                'ResponseCode' => 0000,
                'tokenSend' => $numberoftokens,
                'tokensSubstractedFromWallet' => $requiredAmount,
                'expectedBtcReturn' => $btcAmountToUser ?? 0.0
            ];

        } catch (\Throwable $e) {
            return self::respondWithError($e->getMessage());
        }
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
    }



    /**
     * get LP account tokens.
     * 
     */    
    public function getLpToken()
    {

        $this->logger->info("WalletMapper.getLpToken started");

        $query = "SELECT * from wallett WHERE userid = :userId";
       
		$accounts = $this->pool->returnAccounts();
		$liqpool = $accounts['response'] ?? null;
		$this->poolWallet = $liqpool['pool'];

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':userId', $this->poolWallet, \PDO::PARAM_STR);
            $stmt->execute();
            $walletInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

            $this->logger->info("Inserted new transaction into database");

            return $walletInfo['liquidity'];
        } catch (\PDOException $e) {
            $this->logger->error(
                "WalletMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "WalletMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage()
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        }
    }

    
    /**
     * get LP account info.
     * 
     */    
    public function getLpInfo()
    {

        $this->logger->info("WalletMapper.getLpToken started");

        $query = "SELECT * from wallett WHERE userid = :userId";
       
		$accounts = $this->pool->returnAccounts();
		$liqpool = $accounts['response'] ?? null;
		$this->poolWallet = $liqpool['pool'];

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':userId', $this->poolWallet, \PDO::PARAM_STR);
            $stmt->execute();
            $walletInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

            $this->logger->info("Inserted new transaction into database");

            return $walletInfo;
        } catch (\PDOException $e) {
            $this->logger->error(
                "WalletMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "WalletMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage()
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        }
    }

    /**
     * get BTC LP.
     * 
     * @return BTC Liquidity in account
     */    
    public function getLpTokenBtcLP(): float
    {

        $this->logger->info("WalletMapper.getLpToken started");

        $query = "SELECT * from wallett WHERE userid = :userId";
       
		$accounts = $this->pool->returnAccounts();

		$liqpool = $accounts['response'] ?? null;
		$this->btcpool = $liqpool['btcpool'];

        try {
            $stmt = $this->db->prepare($query);

            $stmt->bindValue(':userId', $this->btcpool, \PDO::PARAM_STR);
            $stmt->execute();
            $walletInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

            $this->logger->info("Inserted new transaction into database");

            return (float) $walletInfo['liquidity'];
        } catch (\PDOException $e) {
            $this->logger->error(
                "WalletMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error(
                "WalletMapper.getLpToken: Exception occurred while getting loop accounts",
                [
                    'error' => $e->getMessage()
                ]
            );
            throw new \RuntimeException("Failed to get accounts: " . $e->getMessage());
        }

        return 0.0;
    }

    
    /**
     * Add New Liquidity
     * 
     * @param int $userId
     * @param array $args
     * @return array
     */
    public function addLiquidity(string $userId, array $args): array
    {
        $this->logger->info("addLiquidity started");

        try {
            // Validate inputs
            $amountPeerToken = $this->validateAmount($args['amountToken'] ?? null, 'PeerToken');
            $amountBtc = $this->validateAmount($args['amountBtc'] ?? null, 'BTC');

            // Fetch pool wallets
            $accountsResult = $this->pool->returnAccounts();
            
            if (isset($accountsResult['status']) && $accountsResult['status'] === 'error') {
                $this->logger->warning('Incorrect returning Accounts', ['Error' => $accountsResult['status']]);
                return self::respondWithError(40701);
            }

            $poolAccounts = $accountsResult['response'] ?? null;
            if (!is_array($poolAccounts) || !isset($poolAccounts['pool'], $poolAccounts['btcpool'])) {
                $this->logger->warning('Missing pool or btcpool account', ['accounts' => $poolAccounts]);
                return self::respondWithError(30102);
            }

            $this->poolWallet = $poolAccounts['pool'];
            $this->btcpool = $poolAccounts['btcpool'];

            // Save PeerToken liquidity
            $this->saveLiquidity(
                $userId,
                $this->poolWallet,
                $amountPeerToken,
                'addPeerTokenLiquidity',
                'ADD_PEER_LIQUIDITY'
            );

            // Save BTC liquidity
            $this->saveLiquidity(
                $userId,
                $this->btcpool,
                $amountBtc,
                'addBtcTokenLiquidity',
                'ADD_BTC_LIQUIDITY'
            );

            return [
                'status' => 'success',
                'ResponseCode' => 0000,
                'newTokenAmount' => $this->getLpToken(),
                'newBtcAmount' => $this->getLpTokenBtcLP(),
                'newTokenPrice' => 0.0 // TODO: Replace with dynamic calculation
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Liquidity error', ['exception' => $e]);
            return self::respondWithError($e->getMessage());
        }
    }

    /**
     * Validate and cast the liquidity amount.
     *
     * @param mixed $value
     * @param string $typeLabel
     * @return float
     */
    private function validateAmount($value, string $typeLabel): float
    {
        $amount = (float) $value;
        if ($amount <= 0 && !is_float($value)) {
            $this->logger->warning("Invalid $typeLabel amount", ['value' => $value]);
            throw new \InvalidArgumentException("Invalid $typeLabel amount");
        }
        return $amount;
    }
    /**
     * Save wallet entry and log transaction.
     *
     * @param int|string $userId
     * @param string $recipientWallet
     * @param float $amount
     * @param string $transactionType
     * @param string $transferAction
     */
    private function saveLiquidity($userId, string $recipientWallet, float $amount, string $transactionType, string $transferAction): void
    {
        $this->saveWalletEntry($recipientWallet, $amount);

        $transaction = new Transaction([
            'transUniqueId' => self::generateUUID(),
            'transactionType' => $transactionType,
            'senderId' => $userId,
            'recipientId' => $recipientWallet,
            'tokenAmount' => $amount,
            'transferAction' => $transferAction,
        ]);

        $repo = new TransactionRepository($this->logger, $this->db);
        $repo->saveTransaction($transaction);
    }

}
