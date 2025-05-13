<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Wallet;
use Fawaz\App\Wallett;
use Psr\Log\LoggerInterface;

class PoolMapper
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_WHEREBY = 100;
    private const ALLOWED_FIELDS = ['userid', 'postid', 'fromid', 'whereby'];

    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    protected function respondWithError(string $message): array
    {
        return ['status' => 'error', 'ResponseCode' => $message];
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
                FROM gems
            ";
            
            $stmt = $this->db->query($sql);
            $entries = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->logger->info('fetching entries for ', ['entries' => $entries]);
        } catch (\Throwable $e) {
            $this->logger->error('Error fetching entries for ', ['exception' => $e->getMessage()]);
            return $this->respondWithError(40301);
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

        $dayOptions = [
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

        if (!array_key_exists($day, $dayOptions)) {
            return $this->respondWithError(30223);
        }

        $whereCondition = $dayOptions[$day];

        $sql = "
            WITH user_sums AS (
                SELECT 
                    userid,
                    GREATEST(COALESCE(SUM(gems), 0), 0) AS total_numbers
                FROM gems
                WHERE {$whereCondition}
                GROUP BY userid
            ),
            total_sum AS (
                SELECT COALESCE(SUM(total_numbers), 0) AS overall_total FROM user_sums
            )
            SELECT 
                g.userid,
                COALESCE(us.total_numbers, 0) AS gems,
                ui.pkey,
               COALESCE(w.liquidity, 0) AS tokenAmount,
                ts.overall_total,
                COALESCE((us.total_numbers * 100.0 / ts.overall_total), 0) AS percentage
            FROM gems g
            JOIN user_sums us ON g.userid = us.userid
            CROSS JOIN total_sum ts
            LEFT JOIN users_info ui ON g.userid = ui.userid
            LEFT JOIN wallett w ON g.userid = w.userid
            WHERE us.total_numbers > 0 AND g.{$whereCondition}
            GROUP BY g.userid, us.total_numbers, ui.pkey, w.liquidity, ts.overall_total;
        ";

        try {
            $stmt = $this->db->query($sql);
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            $this->logger->error('Error reading gems', ['exception' => $e->getMessage()]);
            return $this->respondWithError(40301);
        }

        if (empty($data)) {
            return $this->respondWithError(21202); //'No records found for ' . $day
        }

        $totalGems = isset($data[0]['overall_total']) ? (string)$data[0]['overall_total'] : '0';

        $args = [];

        foreach ($data as $row) {
            $userId = (string)$row['userid'];

            if (!isset($args[$userId])) {
                $args[$userId] = [
                    'userid'      => $userId,
                    'gems'        => $row['gems'] ?? 0,
                    'pkey'        => $row['pkey'] ?? '',
                    'tokenAmount' => $row['tokenAmount'] ?? 0,
                ];
            }

            $whereby = $row['whereby'] ?? null;

            $mapping = [
                1 => ['text' => 'View'],
                2 => ['text' => 'Like'],
                3 => ['text' => 'Dislike'],
                4 => ['text' => 'Comment'],
                5 => ['text' => 'Post'],
            ];

            if ($whereby !== null && isset($mapping[$whereby])) {
                $whereby = $mapping[$whereby]['text'];
            } else {
                $whereby = 'Unknown';
            }
        }

        if (!empty($data)) {

            return [
                'status' => 'success',
                'counter' => count($args) -1,
                'ResponseCode' => 11208,
                'affectedRows' => ['data' => array_values($args), 'totalGems' => $totalGems]
            ];
        }

        return $this->respondWithError(40301);
    }

    private function decimalToQ64_96(float $value): string
    {
        $scaleFactor = bcpow('2', '96');
        
        $scaledValue = bcmul((string)$value, $scaleFactor, 0);
        
        return $scaledValue;
    }
}
