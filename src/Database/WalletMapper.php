<?php

declare(strict_types=1);

namespace Fawaz\Database;


use Fawaz\App\Repositories\WalletRepository;
use PDO;
use Fawaz\App\Wallet;
use Fawaz\App\Wallett;
use Fawaz\Services\LiquidityPool;
use Fawaz\Utils\ResponseHelper;
use Fawaz\Utils\TokenCalculations\TokenHelper;
use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\config\constants\ConstantsConfig;

const TABLESTOGEMS = true;

class WalletMapper implements WalletRepository
{
    use ResponseHelper;
    private const DEFAULT_LIMIT = 20;
    private const MAX_WHEREBY = 100;
    private const ALLOWED_FIELDS = ['userid', 'postid', 'fromid', 'whereby'];
    private string $burnWallet;
    private string $peerWallet;

    public const STATUS_DELETED = 6;

    public function __construct(
        protected PeerLoggerInterface $logger, 
        protected PDO $db, 
        protected LiquidityPool $pool
    ){}

    public function fetchPool(array $args = []): array
    {
        $this->logger->debug('WalletMapper.fetchPool started');

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
                    $results['overall_total_numbers'] = ($row['overall_total_numbers'] ?? 0);
                    $results['overall_total_numbersq'] = (int)$this->decimalToQ64_96((string) $results['overall_total_numbers']);
                }

                $totalNumbers = $row['total_numbers'];
                $totalNumbersQ = (int)$this->decimalToQ64_96((string) $totalNumbers);

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
        $this->logger->debug('WalletMapper.fetchAll started');

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

        $stmt = $this->db->prepare($sql);
        $stmt->execute($queryParams);

        $results = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            try {
                $results[] = new Wallet($row);
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
        $this->logger->debug('WalletMapper.loadWalletById started');

        $userId = $currentUserId;
        $postId = $args['postid'] ?? null;
        $fromId = $args['fromid'] ?? null;

        if (empty($postId) && empty($fromId)) {
            $this->logger->warning('WalletMapper.loadWalletById missing required identifiers in args');
            return false;
        }

        try {
            $offset = max((int)($args['offset'] ?? 0), 0);
            $limit = max((int)($args['limit'] ?? 10), 1);

            $conditions = [];
            $params = [];

            if (empty($userId)) {
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
        $this->logger->debug('WalletMapper.loadLiquidityById started');

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
        $this->logger->debug('WalletMapper.insert started');

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
        $this->logger->debug('WalletMapper.insertt started');

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
        if ($range < 0) {
            return $min;
        }
        $log = \log($range, 2);
        $bytes = (int)($log / 8) + 1;
        $bits = (int)$log + 1;
        $filter = (int)(1 << $bits) - 1;
        do {
            $rnd = \hexdec(\bin2hex(\openssl_random_pseudo_bytes($bytes)));
            $rnd &= $filter;
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
        $this->logger->debug("WalletMapper.fetchWinsLog started for type: $type");

        if (empty($userid)) {
            $this->logger->warning('UserID is missing');
            return self::respondWithError(30101);
        }

        if (!in_array($type, ['win', 'pay'], true)) {
            $this->logger->warning('Type is not provided');
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
            $this->logger->error('WalletMapper.fetchWinsLog exception during logwins query execution', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return self::respondWithError(40301);
        }
    }

    public function insertWinToLog(string $userId, array $args): array|bool
    {
        \ignore_user_abort(true);

        $this->logger->debug('WalletMapper.insertWinToLog started');

        $postId = $args['postid'] ?? null;
        $fromId = $args['fromid'] ?? null;
        $gems = $args['gems'] ?? 0.0;
        $numBers = $args['numbers'] ?? 0;
        $createdat = $args['createdat'] ?? (new \DateTime())->format('Y-m-d H:i:s.u');

        $id = self::generateUUID();

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
            $stmt->bindValue(':numbersq', $this->decimalToQ64_96((string)$numBers), \PDO::PARAM_STR); // 29 char precision
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

    public function insertWinToPool(string $userId, array $args): bool
    {
        \ignore_user_abort(true);

        $this->logger->debug('WalletMapper.insertWinToPool started');

        $postId = $args['postid'] ?? null;
        $fromId = $args['fromid'] ?? null;
        $numBers = $args['numbers'] ?? '0';
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
            $stmt->bindValue(':numbersq', $this->decimalToQ64_96((string)$numBers), \PDO::PARAM_STR); // 29 char precision
            $stmt->bindValue(':whereby', $args['whereby'], \PDO::PARAM_INT);
            $stmt->bindValue(':createdat', $createdat, \PDO::PARAM_STR);

            $stmt->execute();

            $this->saveWalletEntry($userId, (string)$numBers);

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

        $tokenomics = ConstantsConfig::tokenomics();
        $actions = ConstantsConfig::wallet()['ACTIONS'];
        $actionGemsReturns = $tokenomics['ACTION_GEMS_RETURNS'];

        $wins = [
            ['table' => 'user_post_views', 'winType' => (int)$actions['VIEW'], 'factor' => (float)$actionGemsReturns['view']],
            ['table' => 'user_post_likes', 'winType' => (int)$actions['LIKE'], 'factor' => (float)$actionGemsReturns['like']],
            ['table' => 'user_post_dislikes', 'winType' => (int)$actions['DISLIKE'], 'factor' => (float)$actionGemsReturns['dislike']],
            ['table' => 'user_post_comments', 'winType' => (int)$actions['COMMENT'], 'factor' => (float)$actionGemsReturns['comment']]
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
            $success = ['status' => 'success', 'ResponseCode' => "11206"];
            return $success;
        }

        $success = ['status' => 'success', 'ResponseCode' => "21205"];
        return $success;
    }

    protected function setGlobalWins(string $tableName, int $winType, float $factor): array
    {
        \ignore_user_abort(true);

        $this->logger->debug('WalletMapper.setGlobalWins started');

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

        if (empty($entries)) {
            return ['status' => 'success', 'insertCount' => 0];
        }

        $insertCount = 0;
        $entry_ids = [];

        if (!empty($entries)) {
            $entry_ids = array_map(fn ($row) => isset($row['userid']) && is_string($row['userid']) ? $row['userid'] : null, $entries);
            $entry_ids = array_filter($entry_ids);

            $this->db->beginTransaction();

            $sql = "INSERT INTO gems (gemid, userid, postid, fromid, gems, whereby, createdat) 
                    VALUES (:gemid, :userid, :postid, :fromid, :gems, :whereby, :createdat)";
            $stmt = $this->db->prepare($sql);

            try {
                foreach ($entries as $row) {
                    $id = self::generateUUID();

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
                }

                if (!empty($entry_ids)) {
                    $placeholders = implode(',', array_fill(0, count($entry_ids), '?'));
                    $sql = "UPDATE $tableName SET collected = 1 WHERE collected = 0 AND userid IN ($placeholders)";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute($entry_ids);
                }
                $this->db->commit();

            } catch (\Throwable $e) {
                $this->db->rollback();
                $this->logger->error('Error inserting into gems for ' . $tableName, ['exception' => $e]);
                return self::respondWithError(41210);
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
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '6 day' THEN 1 END) AS d6,
                COUNT(CASE WHEN createdat::date = CURRENT_DATE - INTERVAL '7 day' THEN 1 END) AS d7,
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

        return $this::createSuccessResponse(11207, $entries, false);

    }

    public function getTimeSortedMatch(string $day = 'D0'): array
    {
        \ignore_user_abort(true);

        $this->logger->debug('WalletMapper.getTimeSortedMatch started');

        $dayOptionsRaw = [
            "D0" => "createdat::date = CURRENT_DATE",
            "D1" => "createdat::date = CURRENT_DATE - INTERVAL '1 day'",
            "D2" => "createdat::date = CURRENT_DATE - INTERVAL '2 day'",
            "D3" => "createdat::date = CURRENT_DATE - INTERVAL '3 day'",
            "D4" => "createdat::date = CURRENT_DATE - INTERVAL '4 day'",
            "D5" => "createdat::date = CURRENT_DATE - INTERVAL '5 day'",
            "D6" => "createdat::date = CURRENT_DATE - INTERVAL '6 day'",
            "D7" => "createdat::date = CURRENT_DATE - INTERVAL '7 day'",
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
            return self::createSuccessResponse(21206);
        }

        $totalGems = isset($data[0]['overall_total']) ? (string)$data[0]['overall_total'] : '0';
        $dailyToken = (string)(ConstantsConfig::minting()['DAILY_NUMBER_TOKEN']);

        // $gemsintoken = bcdiv("$dailyToken", "$totalGems", 10);
        $gemsintoken = TokenHelper::divRc($dailyToken, $totalGems);

        $bestatigungInitial = TokenHelper::mulRc($totalGems, $gemsintoken);

        $args = [
            'winstatus' => [
                'totalGems' => $totalGems,
                'gemsintoken' => $gemsintoken,
                'bestatigung' => $bestatigungInitial
            ]
        ];

        foreach ($data as $row) {
            $userId = (string)$row['userid'];

            if (!isset($args[$userId])) {

                $totalTokenNumber = TokenHelper::mulRc((string) $row['total_numbers'], $gemsintoken);
                $args[$userId] = [
                    'userid' => $userId,
                    'gems' => (float)$row['total_numbers'],
                    'tokens' => $totalTokenNumber,
                    'percentage' => (float)$row['percentage'],
                    'details' => []
                ];
            }

            $rowgems2token = TokenHelper::mulRc((string) $row['gems'], $gemsintoken);

            $args[$userId]['details'][] = [
                'gemid' => (string)$row['gemid'],
                'userid' => (string)$row['userid'],
                'postid' => (string)$row['postid'],
                'fromid' => (string)$row['fromid'],
                'gems' => (float)$row['gems'],
                'numbers' => (float)$rowgems2token,
                'whereby' => (int)$row['whereby'],
                'createdat' => $row['createdat']
            ];

            $this->insertWinToLog($userId, end($args[$userId]['details']));
            $this->insertWinToPool($userId, end($args[$userId]['details']));
        }


        try {
            $gemIds = array_column($data, 'gemid');
            $quotedGemIds = array_map(fn ($gemId) => $this->db->quote($gemId), $gemIds);

            $this->db->query('UPDATE gems SET collected = 1 WHERE gemid IN (' . \implode(',', $quotedGemIds) . ')');

        } catch (\Throwable $e) {
            $this->logger->error('Error updating gems or liquidity', ['exception' => $e->getMessage()]);
            return self::respondWithError(41212);
        }

        return [
            'status' => 'success',
            'counter' => count($args) - 1,
            'ResponseCode' => "11208",
            'affectedRows' => ['data' => array_values($args), 'totalGems' => $totalGems]
        ];
    }

    public function getUserWalletBalance(string $userId): float
    {
        $this->logger->debug('WalletMapper.getUserWalletBalance started');

        $query = "SELECT COALESCE(liquidity, 0) AS balance 
                  FROM wallett 
                  WHERE userid = :userId FOR UPDATE";

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
                $stmt->bindValue(':updatedat', (new \DateTime())->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);
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
                $stmt->bindValue(':updatedat', (new \DateTime())->format('Y-m-d H:i:s.u'), \PDO::PARAM_STR);

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

    public function callUserMove(string $userId): array
    {
        $tokenomics = ConstantsConfig::tokenomics();
        $actions = ConstantsConfig::wallet()['ACTIONS'];
        $actionGemsReturns = $tokenomics['ACTION_GEMS_RETURNS'];

        try {
            $wins = [
                ['table' => 'user_post_views', 'winType' => (int)$actions['VIEW'], 'factor' => (float)$actionGemsReturns['view'], 'key' => 'views'],
                ['table' => 'user_post_likes', 'winType' => (int)$actions['LIKE'], 'factor' => (float)$actionGemsReturns['like'], 'key' => 'likes'],
                ['table' => 'user_post_dislikes', 'winType' => (int)$actions['DISLIKE'], 'factor' => -(float)$actionGemsReturns['dislike'], 'key' => 'dislikes'],
                ['table' => 'user_post_comments', 'winType' => (int)$actions['COMMENT'], 'factor' => (float)$actionGemsReturns['comment'], 'key' => 'comments']
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
                'ResponseCode' => "41205",
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

    private function decimalToQ64_96(string $value): string
    {
        $scaleFactor = \bcpow('2', '96');

        // Convert float to plain decimal string
        $decimalString = \number_format((float)$value, 30, '.', ''); // 30 decimal places should be enough

        $scaledValue = \bcmul($decimalString, $scaleFactor, 0);

        return $scaledValue;
    }



    /**
     * To Defend against Atomicity issues in concurrent debit operations
     * This function debits the user's wallet only if sufficient funds are available.
     */
    public function debitIfSufficient(string $userId, string $amount): ?string
    {
        $sql = "UPDATE wallett                                                                                                                                                                                                                                                             
                SET liquidity = liquidity - :amt,                                                                                                                                                                                                                                                  
                updatedat = now()                                                                                                                                                                                                                                                                  
                WHERE userid = :uid AND liquidity >= :amt                                                                                                                                                                                                                                          
                RETURNING liquidity";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':amt', $amount, PDO::PARAM_STR);
        $stmt->execute();
        $newBal = $stmt->fetchColumn();
        if ($newBal === false) {
            throw new \RuntimeException('Insufficient funds or user not found', 51301);
        }
        // Update liquidity using returned balance
        $liquiditq = (float)$this->decimalToQ64_96((string)$newBal);

        $q = $this->db->prepare("UPDATE wallett SET liquiditq = :liq_q96 WHERE userid = :uid");
        $q->bindValue(':liq_q96', $liquiditq, PDO::PARAM_STR);
        $q->bindValue(':uid', $userId, PDO::PARAM_STR);
        $q->execute();

        return (string) $newBal;
    }

    /**
     * Credits the user's wallet and updates with Atomicity in mind
     */
    public function credit(string $userId, string $amount): string
    {
        $sql = "UPDATE wallett                                                                                                                                                                                                                                                             
                SET liquidity = liquidity + :amt,                                                                                                                                                                                                                                                  
                updatedat = now()                                                                                                                                                                                                                                                                  
                WHERE userid = :uid                                                                                                                                                                                                                                                                
                RETURNING liquidity";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':uid', $userId, PDO::PARAM_STR);
        $stmt->bindValue(':amt', $amount, PDO::PARAM_STR);
        $stmt->execute();
        $newBal = $stmt->fetchColumn();

        // Should not be false if userid exists; you may validate existence separately
        $liquiditq = (float)$this->decimalToQ64_96((string)$newBal);

        $q = $this->db->prepare("UPDATE wallett SET liquiditq = :liq_q96 WHERE userid = :uid");
        $q->bindValue(':liq_q96', $liquiditq, PDO::PARAM_STR);
        $q->bindValue(':uid', $userId, PDO::PARAM_STR);
        $q->execute();

        return (string)$newBal;
    }

    /**
     * Lock a single wallet balance for update.
     */
    public function lockWalletBalance(string $walletId): void
    {
        $query = "SELECT liquidity FROM wallett WHERE userid = :userid FOR UPDATE";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':userid', $walletId, \PDO::PARAM_STR);
        $stmt->execute();
        // Fetching the row to ensure the lock is acquired
        $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
