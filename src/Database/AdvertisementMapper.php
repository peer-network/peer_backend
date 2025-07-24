<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Advertisements;
use Psr\Log\LoggerInterface;

class AdvertisementMapper
{
    public function __construct(protected LoggerInterface $logger, protected PDO $db)
    {
    }

    public function fetchAllWithStats(?array $args = []): array
    {
        $this->logger->info("AdvertisementMapper.fetchAllWithStats started");

        $offset = $args['offset'] ?? 0;
        $limit = $args['limit'] ?? 10;
        $filterBy = $args['filter'] ?? [];
        $sortBy = strtoupper($args['sort'] ?? 'NEWEST');

        $conditions = [];
        $params = [];

        if (!empty($filterBy)) {
            if (!empty($filterBy['from'])) {
                $conditions[] = "al.createdat >= :from";
                $params[':from'] = $filterBy['from'];
            }

            if (!empty($filterBy['to'])) {
                $conditions[] = "al.createdat <= :to";
                $params[':to'] = $filterBy['to'];
            }

            if (!empty($filterBy['type'])) {
                $typeMap = [
                    'PINNED' => 'pinned',
                    'BASIC' => 'basic',
                ];
                if (isset($typeMap[$filterBy['type']])) {
                    $conditions[] = "al.status = :type";
                    $params[':type'] = $typeMap[$filterBy['type']];
                }
            }

            if (!empty($filterBy['advertisementId'])) {
                $conditions[] = "al.advertisementid = :advertisementId";
                $params[':advertisementId'] = $filterBy['advertisementId'];
            }

            if (!empty($filterBy['postId'])) {
                $conditions[] = "al.postid = :postId";
                $params[':postId'] = $filterBy['postId'];
            }

            if (!empty($filterBy['userId'])) {
                $conditions[] = "al.userid = :userId";
                $params[':userId'] = $filterBy['userId'];
            }
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $orderByMap = [
            'NEWEST' => 'al.createdat DESC',
            'OLDEST' => 'al.createdat ASC',
            'BIGGEST_COST' => 'al.tokencost DESC',
            'SMALLEST_COST' => 'al.tokencost ASC',
        ];

        $orderByClause = $orderByMap[$sortBy] ?? $orderByMap['NEWEST'];

        $sSql = "
            SELECT 
                COALESCE(SUM(al.tokencost), 0) AS total_token_spent,
                COALESCE(SUM(al.eurocost), 0) AS total_euro_spent,
                COUNT(al.postid) AS total_ads,
                COALESCE(SUM(g.gems), 0) AS total_gems_earned
            FROM advertisements_log al
            LEFT JOIN gems g ON g.postid = al.postid AND g.collected = 1
            $whereClause
        ";

        $dSql = "
            SELECT 
                al.advertisementid,
                al.createdat,
                al.status,
                al.userid,
                al.postid,
                al.timestart,
                al.timeend,
                al.tokencost,
                al.eurocost,
                (
                    SELECT COALESCE(SUM(g2.gems), 0)
                    FROM gems g2
                    WHERE g2.postid = al.postid AND g2.collected = 1
                ) AS gemsEarned,
                (
                    SELECT COUNT(pv.postid)
                    FROM user_post_views pv
                    WHERE pv.postid = al.postid
                ) AS view
            FROM advertisements_log al
            $whereClause
            ORDER BY $orderByClause
            LIMIT :limit OFFSET :offset
        ";

        try {
            $statsStmt = $this->db->prepare($sSql);
            foreach ($params as $key => $val) {
                $statsStmt->bindValue($key, $val);
            }
            $statsStmt->execute();
            $stats = $statsStmt->fetch(\PDO::FETCH_ASSOC);

            $dataStmt = $this->db->prepare($dSql);
            foreach ($params as $key => $val) {
                $dataStmt->bindValue($key, $val);
            }
            $dataStmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $dataStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $dataStmt->execute();
            $ads = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);

            return [
                'counter' => count($ads),
                'affectedRows' => [
                    'stats' => [
                        'totaltokenspent' => (float)($stats['total_token_spent'] ?? 0),
                        'totaleurospent' => (float)($stats['total_euro_spent'] ?? 0),
                        'totalads' => (int)($stats['total_ads'] ?? 0),
                        'totalgemsearned' => (float)($stats['total_gems_earned'] ?? 0),
                    ],
                    'advertisements' => $ads,
                ],
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Error fetching advertisement stats or data", [
                'error' => $e->getMessage(),
            ]);
            return [
                'counter' => 0,
                'affectedRows' => [
                    'stats' => null,
                    'advertisements' => [],
                ],
            ];
        }
    }

    public function isAdvertisementDurationValid(string $postId, string $userId): bool
    {
        $this->logger->info("AdvertisementMapper.isAdvertisementDurationValid started");

        $sql = "
            SELECT EXTRACT(EPOCH FROM (timeend - timestart)) / 60 AS duration_minutes
            FROM advertisements
            WHERE timestart < NOW() 
            AND timeend > NOW() 
            AND postid = :postId 
            AND userid = :userId 
            AND status = 'basic'
            LIMIT 1
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':postId', $postId, \PDO::PARAM_STR);
            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['duration_minutes'])) {
                $durationMinutes = (float)$result['duration_minutes'];
                $isValid = $durationMinutes >= 1440;
                $this->logger->info("Duration calculated", ['minutes' => $durationMinutes, 'isValid' => $isValid]);
                return $isValid;
            }

            $this->logger->info("No valid advertisement found for given postId");
            return false;
        } catch (\Throwable $e) {
            $this->logger->error("Error checking advertisement duration", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function hasShortActiveAdWithUpcomingAd(string $postId, string $userId): bool
    {
        $this->logger->info("AdvertisementMapper.hasShortActiveAdWithUpcomingAd started", [
            'postid' => $postId,
            'userid' => $userId
        ]);

        $sql = "
            SELECT 
                EXISTS (
                    SELECT 1 FROM advertisements
                    WHERE postid = :postId
                      AND userid = :userId
                      AND status = 'basic'
                      AND timestart < NOW()
                      AND timeend > NOW()
                      AND EXTRACT(EPOCH FROM (timeend - NOW())) / 60 < 1440
                ) AS has_short_active,

                EXISTS (
                    SELECT 1 FROM advertisements
                    WHERE postid = :postId
                      AND userid = :userId
                      AND timestart BETWEEN NOW() AND NOW() + INTERVAL '24 HOURS'
                ) AS has_upcoming;
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':postId', $postId, \PDO::PARAM_STR);
            $stmt->bindValue(':userId', $userId, \PDO::PARAM_STR);
            $stmt->execute();

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $this->logger->info("Advertisement timing check result", $result);

            return $result['has_short_active'] && $result['has_upcoming'];
        } catch (\Throwable $e) {
            $this->logger->error("Error in hasShortActiveAdWithUpcomingAd", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function fetchByAdvID(string $postId, string $status): array
    {
        $this->logger->info("AdvertisementMapper.fetchByAdvID started", [
            'postId' => $postId,
            'status' => $status
        ]);

        $sql = "SELECT advertisementid, postid, userid, status, timestart, timeend 
                FROM advertisements 
                WHERE postid = :postId AND status = :status";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':postId', $postId, \PDO::PARAM_STR);
            $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
            $stmt->execute();

            $results = array_map(fn($row) => new Advertisements($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));

            $this->logger->info(
                $results ? "Fetched advertisementId successfully" : "No advertisements found for userid.",
                ['count' => count($results)]
            );

            return $results;
        } catch (\Throwable $e) {
            $this->logger->error("Error fetching advertisementId from database", [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function isAdvertisementIdExist(string $postId, string $status): bool
    {
        $this->logger->info("AdvertisementMapper.isAdvertisementIdExist started");

        $sql = "SELECT 1 FROM advertisements WHERE postid = :postId AND status = :status LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':postId', $postId, \PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public function hasTimeConflict(string $postId, string $status, string $newStart, string $newEnd): bool
    {
        $this->logger->info("AdvertisementMapper.hasTimeConflict started");

        $this->logger->info("Checking for conflicting reservations", [
            'postId' => $postId,
            'status' => $status,
            'newStart' => $newStart,
            'newEnd' => $newEnd
        ]);

        $sql = "SELECT COUNT(*) 
                FROM advertisements 
                WHERE postid = :postId
                  AND status = :status
                  AND timeend > :newStart
                  AND timestart < :newEnd";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':postId', $postId, \PDO::PARAM_STR);
            $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
            $stmt->bindValue(':newStart', $newStart, \PDO::PARAM_STR);
            $stmt->bindValue(':newEnd', $newEnd, \PDO::PARAM_STR);
            $stmt->execute();

            $count = (int) $stmt->fetchColumn();

            $this->logger->info("Conflict check result", ['conflicts' => $count]);

            return $count > 0;
        } catch (\Throwable $e) {
            $this->logger->error("Error checking reservation conflicts", [
                'error' => $e->getMessage()
            ]);
            return true;
        }
    }

    // Create a Post Advertisement with Loging
    public function insert(Advertisements $post): Advertisements
    {
        $this->logger->info("AdvertisementMapper.insert started");

        $data = $post->getArrayCopy();

        // SQL-Statements für beide Tabellen
        $query1 = "INSERT INTO advertisements 
                   (advertisementid, postid, userid, status, timestart, timeend)
                   VALUES 
                   (:advertisementid, :postid, :userid, :status, :timestart, :timeend)";

        $query2 = "INSERT INTO advertisements_log 
                   (advertisementid, postid, userid, status, timestart, timeend, tokencost, eurocost)
                   VALUES 
                   (:advertisementid, :postid, :userid, :status, :timestart, :timeend, :tokencost, :eurocost)";

        try {
            $this->db->beginTransaction();

            // Statement 1
            $stmt1 = $this->db->prepare($query1);
            if (!$stmt1) {
                throw new \RuntimeException("SQL prepare() failed: " . implode(", ", $this->db->errorInfo()));
            }

            foreach (['advertisementid', 'postid', 'userid', 'status', 'timestart', 'timeend'] as $key) {
                $stmt1->bindValue(':' . $key, $data[$key], \PDO::PARAM_STR);
            }

            $stmt1->execute();

            // Statement 2
            $stmt2 = $this->db->prepare($query2);
            if (!$stmt2) {
                throw new \RuntimeException("SQL prepare() failed: " . implode(", ", $this->db->errorInfo()));
            }

            foreach (['advertisementid', 'postid', 'userid', 'status', 'timestart', 'timeend', 'tokencost', 'eurocost'] as $key) {
                $stmt2->bindValue(':' . $key, $data[$key], \PDO::PARAM_STR);
            }

            $stmt2->execute();

            $this->db->commit();

            $this->logger->info("Inserted new PostAdvertisement into both tables");
            return new Advertisements($data);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error("insert: Exception occurred while insertng", ['error' => $e->getMessage()]);
            throw new \RuntimeException("Failed to insert PostAdvertisement: " . $e->getMessage());
        }
    }

    // Update a Post Advertisement with Logging
    public function update(Advertisements $post): Advertisements
    {
        $this->logger->info("AdvertisementMapper.update started");

        $data = $post->getArrayCopy();

        $query1 = "UPDATE advertisements 
                    SET timestart = :timestart, timeend = :timeend, userid = :userid
                    WHERE postid = :postid AND status = :status";

        $query2 = "INSERT INTO advertisements_log 
                    (advertisementid, postid, userid, status, timestart, timeend, tokencost, eurocost) 
                    VALUES (:advertisementid, :postid, :userid, :status, :timestart, :timeend, :tokencost, :eurocost)";

        try {
            $this->db->beginTransaction();

            $stmt1 = $this->db->prepare($query1);
            $stmt1->bindValue(':timestart', $data['timestart'], \PDO::PARAM_STR);
            $stmt1->bindValue(':timeend', $data['timeend'], \PDO::PARAM_STR);
            $stmt1->bindValue(':userid', $data['userid'], \PDO::PARAM_STR);
            $stmt1->bindValue(':postid', $data['postid'], \PDO::PARAM_STR);
            $stmt1->bindValue(':status', $data['status'], \PDO::PARAM_STR);

            $stmt1->execute();

            $stmt2 = $this->db->prepare($query2);
            foreach (['advertisementid', 'postid', 'userid', 'status', 'timestart', 'timeend', 'tokencost', 'eurocost'] as $key) {
                $stmt2->bindValue(':' . $key, $data[$key], \PDO::PARAM_STR);
            }
            $stmt2->execute();

            $this->db->commit();

            $this->logger->info("Updated Post Advertisement & inserted into Log");
            return new Advertisements($data);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            $this->logger->error("update: Exception occurred while updating", ['error' => $e->getMessage()]);
            throw new \RuntimeException("Failed to update PostAdvertisement: " . $e->getMessage());
        }
    }

    public function convertEuroToTokens(float $euroAmount, int $rescode): array
    {
        $this->logger->info('AdvertisementMapper.convertEuroToTokens started', ['euroAmount' => $euroAmount]);

        $tokenPrice = 0.01; // Fixed price: 1 cent
        $tokens = $euroAmount / $tokenPrice;

        $response = [
            'status' => 'success',
            'ResponseCode' => $rescode,
            'affectedRows' => [
                'InputEUR' => round($euroAmount, 2),
                'TokenPriceFixedEUR' => $tokenPrice,
                'TokenAmount' => floor($tokens),
            ]
        ];

        $this->logger->info('convertEuroToTokens response', ['response' => $response]);
        return $response;
    }
}
