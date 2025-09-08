<?php

namespace Fawaz\Database;

use PDO;
use Fawaz\App\Advertisements;
use Fawaz\App\PostAdvanced;
use Fawaz\App\Role;
use Fawaz\App\Status;
use Fawaz\config\constants\ConstantsConfig;
use Fawaz\Services\ContentFiltering\ContentFilterServiceImpl;
use Fawaz\Services\ContentFiltering\ContentReplacementPattern;
use Fawaz\Services\ContentFiltering\Strategies\ListPostsContentFilteringStrategy;
use Fawaz\Services\ContentFiltering\Types\ContentFilteringAction;
use Fawaz\Services\ContentFiltering\Types\ContentType;
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
                   (advertisementid, postid, userid, status, timestart, timeend, createdat)
                   VALUES 
                   (:advertisementid, :postid, :userid, :status, :timestart, :timeend, :createdat)";

        $query2 = "INSERT INTO advertisements_log 
                   (advertisementid, postid, userid, status, timestart, timeend, tokencost, eurocost, createdat)
                   VALUES 
                   (:advertisementid, :postid, :userid, :status, :timestart, :timeend, :tokencost, :eurocost, :createdat)";

        try {
            $this->db->beginTransaction();

            // Statement 1
            $stmt1 = $this->db->prepare($query1);
            if (!$stmt1) {
                throw new \RuntimeException("SQL prepare() failed: " . implode(", ", $this->db->errorInfo()));
            }

            foreach (['advertisementid', 'postid', 'userid', 'status', 'timestart', 'timeend','createdat'] as $key) {
                $stmt1->bindValue(':' . $key, $data[$key], \PDO::PARAM_STR);
            }

            $stmt1->execute();

            // Statement 2
            $stmt2 = $this->db->prepare($query2);
            if (!$stmt2) {
                throw new \RuntimeException("SQL prepare() failed: " . implode(", ", $this->db->errorInfo()));
            }

            foreach (['advertisementid', 'postid', 'userid', 'status', 'timestart', 'timeend', 'tokencost', 'eurocost','createdat'] as $key) {
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

        $tokenPrice = 0.10; // Fixed price: 10 cent
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

    public function findAdvertiser(string $currentUserId, ?array $args = []): array
    {
        $this->logger->info("AdvertisementMapper.findAdvertiser started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit  = min(max((int)($args['limit'] ?? 10), 1), 20);
        $trenddays = 7;

        $from   = $args['from']   ?? null;
        $to     = $args['to']     ?? null;
        $filterBy = $args['filterBy'] ?? [];
        $tag    = $args['tag']    ?? null;
        $postId = $args['postid'] ?? null;
        $userId = $args['userid'] ?? null;

        $whereClauses = ["p.feedid IS NULL"];
        $params = ['currentUserId' => $currentUserId];

        if ($postId !== null) {
            $whereClauses[] = "p.postid = :postId";
            $params['postId'] = $postId;
        }
        if ($userId !== null) {
            $whereClauses[] = "p.userid = :userId";
            $params['userId'] = $userId;
        }
        if ($from !== null) {
            $whereClauses[] = "p.createdat >= :from";
            $params['from'] = $from;
        }
        if ($to !== null) {
            $whereClauses[] = "p.createdat <= :to";
            $params['to'] = $to;
        }
        if ($tag !== null) {
            $whereClauses[] = "t.name = :tag";
            $params['tag'] = $tag;
        }

        // Allow Only (Normal Status) Plus (User's & Admin's Mode) Posts
        $whereClauses[] = 'u.status = :stNormal AND u.roles_mask IN (:roleUser, :roleAdmin)';
        $params['stNormal']  = Status::NORMAL;
        $params['roleUser']  = Role::USER;
        $params['roleAdmin'] = Role::ADMIN;

        // FilterBy Content Types
        if (!empty($filterBy) && is_array($filterBy)) {
            $mapping = [
                'IMAGE' => 'image',
                'AUDIO' => 'audio',
                'VIDEO' => 'video',
                'TEXT'  => 'text',
            ];

            $validTypes = array_values(array_intersect_key($mapping, array_flip($filterBy)));

            if ($validTypes) {
                $placeholders = [];
                foreach ($validTypes as $i => $value) {
                    $key = "filter$i";
                    $placeholders[] = ":$key";
                    $params[$key] = $value;
                }
                $whereClauses[] = "p.contenttype IN (" . implode(", ", $placeholders) . ")";
            }
        }

        $baseSelect = "
            SELECT 
                p.postid, p.userid, p.contenttype, p.title, p.media, p.cover, p.mediadescription, p.createdat,
                a.advertisementid, a.userid AS tuserid, a.status AS ad_type,
                a.timestart AS ad_order, a.timeend AS end_order, a.createdat AS tcreatedat,
                ut.username AS tusername, ut.slug AS tslug, ut.img AS timg,
                u.username, u.slug, u.img,
                COALESCE(JSON_AGG(t.name) FILTER (WHERE t.name IS NOT NULL), '[]'::json) AS tags,
                (SELECT COUNT(*) FROM user_post_likes WHERE postid = p.postid) AS amountlikes,
                (SELECT COUNT(*) FROM user_post_dislikes WHERE postid = p.postid) AS amountdislikes,
                (SELECT COUNT(*) FROM user_post_views WHERE postid = p.postid) AS amountviews,
                (SELECT COUNT(*) FROM comments WHERE postid = p.postid) AS amountcomments,
                COALESCE((SELECT SUM(numbers) FROM logwins WHERE postid = p.postid AND createdat >= NOW() - INTERVAL '$trenddays days'), 0) AS amounttrending,
                EXISTS (SELECT 1 FROM user_post_likes     WHERE postid = p.postid AND userid = :currentUserId) AS isliked,
                EXISTS (SELECT 1 FROM user_post_views     WHERE postid = p.postid AND userid = :currentUserId) AS isviewed,
                EXISTS (SELECT 1 FROM user_post_reports   WHERE postid = p.postid AND userid = :currentUserId) AS isreported,
                EXISTS (SELECT 1 FROM user_post_dislikes  WHERE postid = p.postid AND userid = :currentUserId) AS isdisliked,
                EXISTS (SELECT 1 FROM user_post_saves     WHERE postid = p.postid AND userid = :currentUserId) AS issaved,
                EXISTS (SELECT 1 FROM follows WHERE followedid = a.userid AND followerid = :currentUserId) AS tisfollowed,
                EXISTS (SELECT 1 FROM follows WHERE followerid = a.userid AND followedid = :currentUserId) AS tisfollowing,
                EXISTS (SELECT 1 FROM follows WHERE followedid = p.userid AND followerid = :currentUserId) AS isfollowed,
                EXISTS (SELECT 1 FROM follows WHERE followerid = p.userid AND followedid = :currentUserId) AS isfollowing
            FROM posts p
            JOIN users u ON p.userid = u.uid
            LEFT JOIN post_tags pt ON p.postid = pt.postid
            LEFT JOIN tags t ON pt.tagid = t.tagid
            LEFT JOIN advertisements a ON p.postid = a.postid
            LEFT JOIN users ut ON a.userid = ut.uid
            WHERE " . implode(" AND ", $whereClauses) . "
            GROUP BY p.postid, a.advertisementid,
                     tuserid, ad_type, ad_order, end_order,
                     tcreatedat, tusername, tslug, timg,
                     u.username, u.slug, u.img
        ";

        $params['limit'] = $limit;
        $params['offset'] = $offset;

        $sqlPinnedPosts = "WITH base_posts AS ($baseSelect)
        SELECT * FROM base_posts
        WHERE ad_type = 'pinned'
          AND ad_order <= NOW()
          AND end_order > NOW()
        ORDER BY ad_order DESC
        LIMIT :limit OFFSET :offset";

        $sqlBasicAds = "WITH base_posts AS ($baseSelect)
        SELECT * FROM base_posts
        WHERE ad_type = 'basic'
          AND ad_order <= NOW()
          AND end_order > NOW()
        ORDER BY ad_order ASC
        LIMIT :limit OFFSET :offset";

        try {
            $pinnedStmt = $this->db->prepare($sqlPinnedPosts);
            foreach ($params as $key => $val) {
                $pinnedStmt->bindValue(":" . $key, $val);
            }
            $pinnedStmt->execute();
            $pinned = $pinnedStmt->fetchAll(\PDO::FETCH_ASSOC);

            $basicStmt = $this->db->prepare($sqlBasicAds);
            foreach ($params as $key => $val) {
                $basicStmt->bindValue(":" . $key, $val);
            }
            $basicStmt->execute();
            $basic = $basicStmt->fetchAll(\PDO::FETCH_ASSOC);

            $finalPosts = [];
            if (!empty($pinned)) {
                $finalPosts = array_merge($finalPosts, $pinned);
            }
            if (!empty($basic)) {
                $finalPosts = array_merge($finalPosts, $basic);
            }

            return array_map(function ($row) {
                $row['tags'] = json_decode($row['tags'], true) ?? [];

                return [
                    'post' => self::mapRowToPost($row),
                    'advertisement' => self::mapRowToAdvertisement($row),
                ];
            }, $finalPosts);

        } catch (\Throwable $e) {
            $this->logger->error('General error in findAdvertiser', [
                'error' => $e,
            ]);
            return [];
        }
    }

    private static function mapRowToPost(array $row): PostAdvanced
    {
        return new PostAdvanced([
            'postid' => (string)$row['postid'],
            'userid' => (string)$row['userid'],
            'contenttype' => (string)$row['contenttype'],
            'title' => (string)$row['title'],
            'media' => (string)$row['media'],
            'cover' => (string)$row['cover'],
            'mediadescription' => (string)$row['mediadescription'],
            'createdat' => (string)$row['createdat'],
            'amountlikes' => (int)$row['amountlikes'],
            'amountviews' => (int)$row['amountviews'],
            'amountcomments' => (int)$row['amountcomments'],
            'amountdislikes' => (int)$row['amountdislikes'],
            'amounttrending' => (int)$row['amounttrending'],
            'isliked' => (bool)$row['isliked'],
            'isviewed' => (bool)$row['isviewed'],
            'isreported' => (bool)$row['isreported'],
            'isdisliked' => (bool)$row['isdisliked'],
            'issaved' => (bool)$row['issaved'],
            'tags' => $row['tags'],
            'user' => [
                'uid' => (string)$row['userid'],
                'username' => (string)$row['username'],
                'slug' => (int)$row['slug'],
                'img' => (string)$row['img'],
                'isfollowed' => (bool)$row['isfollowed'],
                'isfollowing' => (bool)$row['isfollowing'],
                'isfriend' => (bool)((int)$row['isfollowed'] && (int)$row['isfollowing']),
            ],
        ]);
    }

    private static function mapRowToAdvertisement(array $row): Advertisements
    {
        return new Advertisements([
            'advertisementid' => (string)$row['advertisementid'],
            'postid' => (string)$row['postid'],
            'userid' => (string)$row['tuserid'],
            'status' => (string)$row['ad_type'],
            'timestart' => (string)$row['ad_order'],
            'timeend' => (string)$row['end_order'],
            'createdat' => (string)$row['tcreatedat'],
            'user' => [
                'uid' => (string)$row['tuserid'],
                'username' => (string)$row['tusername'],
                'slug' => (string)$row['tslug'],
                'img' => (string)$row['timg'],
                'isfollowed' => (bool)$row['tisfollowed'],
                'isfollowing' => (bool)$row['tisfollowing'],
                'isfriend' => (bool)((int)$row['tisfollowed'] && (int)$row['tisfollowing']),
            ],
        ]);
    }
}
