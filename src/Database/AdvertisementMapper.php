<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\Utils\ContentFilterHelper;
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
use Fawaz\Utils\PeerLoggerInterface;

class AdvertisementMapper
{
    public function __construct(protected PeerLoggerInterface $logger, protected PDO $db)
    {
    }

    public function fetchAllWithStats(?array $args = []): array
    {
        $this->logger->debug("AdvertisementMapper.fetchAllWithStats started");

        $offset    = isset($args['offset']) ? (int)$args['offset'] : 0;
        $limit     = isset($args['limit']) ? (int)$args['limit'] : 10;
        $filterBy  = $args['filter'] ?? [];
        $sortBy    = strtoupper($args['sort'] ?? 'NEWEST');
        $trendDays = isset($args['trenddays']) ? (int)$args['trenddays'] : 7;

        $trendSince = (new \DateTimeImmutable('now'))
            ->modify("-{$trendDays} days")
            ->format('Y-m-d H:i:s');

        // ---- Filterbedingungen + gemeinsame Parameter
        $conditions   = [];
        $paramsCommon = [];

        if (!empty($filterBy['from'])) {
            $conditions[] = "al.createdat >= :from";
            $paramsCommon[':from'] = $filterBy['from'];
        }
        if (!empty($filterBy['to'])) {
            $conditions[] = "al.createdat <= :to";
            $paramsCommon[':to'] = $filterBy['to'];
        }
        if (!empty($filterBy['type'])) {
            $typeMap = ['PINNED' => 'pinned', 'BASIC' => 'basic'];
            if (isset($typeMap[$filterBy['type']])) {
                $conditions[] = "al.status = :type";
                $paramsCommon[':type'] = $typeMap[$filterBy['type']];
            }
        }
        if (!empty($filterBy['advertisementId'])) {
            $conditions[] = "al.advertisementid = :advertisementId";
            $paramsCommon[':advertisementId'] = $filterBy['advertisementId'];
        }
        if (!empty($filterBy['postId'])) {
            $conditions[] = "al.postid = :postId";
            $paramsCommon[':postId'] = $filterBy['postId'];
        }
        if (!empty($filterBy['userId'])) {
            $conditions[] = "al.userid = :userId";
            $paramsCommon[':userId'] = $filterBy['userId'];
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // ---- Sortierung
        $orderByMap = [
            'NEWEST'        => 'al.createdat DESC',
            'OLDEST'        => 'al.createdat ASC',
            'BIGGEST_COST'  => 'al.tokencost DESC',
            'SMALLEST_COST' => 'al.tokencost ASC',
        ];
        $orderByClause = $orderByMap[$sortBy] ?? $orderByMap['NEWEST'];

        // ---- STATS: distinct Ads + Gems pro Ad-Zeitfenster
        $sSql = "
            WITH al_filtered AS (
                SELECT *
                FROM advertisements_log al
                $whereClause
            ),
            ad_gems AS (
                SELECT
                    al.advertisementid,
                    COALESCE(SUM(lw.gems), 0) AS gems_earned
                FROM al_filtered al
                LEFT JOIN logwins lw
                  ON lw.postid = al.postid
                 AND lw.createdat >= al.timestart
                 AND lw.createdat <  al.timeend
                GROUP BY al.advertisementid
            ),
            -- für Post-weite Summen (Likes/Views/...)
            post_set AS (
                SELECT DISTINCT postid FROM al_filtered
            ),
            likes_by_post AS (
                SELECT postid, SUM(likes) AS cnt
                FROM advertisements_info
                GROUP BY postid
            ),
            dislikes_by_post AS (
                SELECT postid, SUM(dislikes) AS cnt
                FROM advertisements_info
                GROUP BY postid
            ),
            views_by_post AS (
                SELECT postid, SUM(views) AS cnt
                FROM advertisements_info
                GROUP BY postid
            ),
            comments_by_post AS (
                SELECT postid, SUM(comments) AS cnt
                FROM advertisements_info
                GROUP BY postid
            ),
            reports_by_post AS (
                SELECT postid, SUM(reports) AS cnt
                FROM advertisements_info
                GROUP BY postid
            )
            SELECT
                COALESCE(SUM(al_filtered.tokencost), 0)                 AS total_token_spent,
                COALESCE(SUM(al_filtered.eurocost), 0)                  AS total_euro_spent,
                COUNT(*) AS total_ads,
                COALESCE((SELECT SUM(gems_earned) FROM ad_gems), 0)     AS total_gems_earned,
                COALESCE((SELECT SUM(lb.cnt) FROM post_set ps LEFT JOIN likes_by_post    lb USING (postid)), 0) AS total_amount_likes,
                COALESCE((SELECT SUM(vb.cnt) FROM post_set ps LEFT JOIN views_by_post    vb USING (postid)), 0) AS total_amount_views,
                COALESCE((SELECT SUM(cb.cnt) FROM post_set ps LEFT JOIN comments_by_post cb USING (postid)), 0) AS total_amount_comments,
                COALESCE((SELECT SUM(db.cnt) FROM post_set ps LEFT JOIN dislikes_by_post db USING (postid)), 0) AS total_amount_dislikes,
                COALESCE((SELECT SUM(rp.cnt) FROM post_set ps LEFT JOIN reports_by_post  rp USING (postid)), 0) AS total_amount_reports
            FROM al_filtered
        ";

        // ---- DATA: 1 Row je Anzeige (neueste Log-Zeile), gemsearned pro Anzeige-Zeitfenster
        $dSql = "
			WITH al_filtered AS (
			  SELECT *
			  FROM advertisements_log al
			  $whereClause
			),
			-- HIER der einzige Change: latest_al ist jetzt einfach al_filtered (keine ROW_NUMBER/DISTINCT mehr)
			latest_al AS (
			  SELECT * FROM al_filtered
			),
			ad_gems AS (
			  SELECT
				  al.advertisementid,
				  COALESCE(SUM(lw.gems), 0)    AS gems_earned,
				  COALESCE(SUM(lw.numbers), 0) AS numbers_earned
			  FROM latest_al al
			  LEFT JOIN logwins lw
				ON lw.postid = al.postid
			   AND lw.createdat >= al.timestart
			   AND lw.createdat <  al.timeend
			  GROUP BY al.advertisementid
			),
			tags_by_post AS (
			  SELECT pt.postid, COALESCE(JSON_AGG(t.name) FILTER (WHERE t.name IS NOT NULL), '[]'::json) AS tags
			  FROM post_tags pt
			  JOIN tags t ON t.tagid = pt.tagid
			  GROUP BY pt.postid
			)
			SELECT
			  -- ab hier ALLES wie vorher, nur dass FROM latest_al al jetzt ALLE Log-Zeilen liefert
			  al.id               AS log_id,
			  al.advertisementid  AS advertisementid,
			  al.postid           AS postid,
			  al.userid           AS creatorid,
			  al.status           AS status,
			  al.timestart        AS timeframestart,
			  al.timeend          AS timeframeend,
			  al.tokencost        AS totaltokencost,
			  al.eurocost         AS totaleurocost,
			  al.createdat        AS createdat,

			  COALESCE(ag.gems_earned,    0) AS gemsearned,
			  COALESCE(ag.numbers_earned, 0) AS numbersearned,

			  p.userid                         AS post_owner_id,
			  p.createdat                      AS post_createdat,
			  p.contenttype                    AS contenttype,
			  p.title                          AS title,
			  p.media                          AS media,
			  p.cover                          AS cover,
			  p.mediadescription               AS mediadescription,

			  m.amountlikes                    AS amountlikes,
			  m.amountviews                    AS amountviews,
			  m.amountcomments                 AS amountcomments,
			  m.amountdislikes                 AS amountdislikes,
			  m.amountreports                  AS amountreports,
			  m.amounttrending                 AS amounttrending,
			  (m.isliked      )::int           AS isliked,
			  (m.isviewed     )::int           AS isviewed,
			  (m.isreported   )::int           AS isreported,
			  (m.isdisliked   )::int           AS isdisliked,
			  (m.issaved      )::int           AS issaved,
			  tg.tags                          AS tags,

			  cm.comments                      AS comments,

			  u.username                       AS creator_username,
			  u.slug                           AS creator_slug,
			  u.img                            AS creator_img,

			  pu.username                      AS post_username,
			  pu.slug                          AS post_slug,
			  pu.img                           AS post_userimg,

			  (m.isfollowed   )::int           AS isfollowed,
			  (m.isfollowing  )::int           AS isfollowing,
			  (m.isfriend     )::int           AS isfriend,

			  COALESCE(ai.amountlikes,    0)   AS adsamountlikes,
			  COALESCE(ai.amountviews,    0)   AS adsamountviews,
			  COALESCE(ai.amountcomments, 0)   AS adsamountcomments,
			  COALESCE(ai.amountdislikes, 0)   AS adsamountdislikes,
			  COALESCE(ai.amountreports,  0)   AS adsamountreports

			FROM latest_al al
			LEFT JOIN ad_gems ag      ON ag.advertisementid = al.advertisementid
			LEFT JOIN posts p         ON p.postid   = al.postid
			LEFT JOIN users u         ON u.uid      = al.userid
			LEFT JOIN users pu        ON pu.uid     = p.userid

			LEFT JOIN LATERAL (
			  SELECT
				  ai.likes    AS amountlikes,
				  ai.dislikes AS amountdislikes,
				  ai.views    AS amountviews,
				  ai.reports  AS amountreports,
				  ai.comments AS amountcomments
			  FROM advertisements_info ai
			  WHERE ai.advertisementid = al.advertisementid
				AND ai.postid          = p.postid
			  LIMIT 1
			) ai ON TRUE

			LEFT JOIN LATERAL (
			  SELECT
				  (SELECT COUNT(*) FROM user_post_likes    upl WHERE upl.postid = p.postid) AS amountlikes,
				  (SELECT COUNT(*) FROM user_post_dislikes upd WHERE upd.postid = p.postid) AS amountdislikes,
				  (SELECT COUNT(*) FROM user_post_views    upv WHERE upv.postid = p.postid) AS amountviews,
				  (SELECT COUNT(*) FROM user_post_reports  upr WHERE upr.postid = p.postid) AS amountreports,
				  (SELECT COUNT(*) FROM comments           cmt WHERE cmt.postid = p.postid)  AS amountcomments,
				  COALESCE((
					  SELECT SUM(numbers) FROM logwins lw
					  WHERE lw.postid = p.postid AND lw.createdat >= :trend_since
				  ), 0) AS amounttrending,
				  EXISTS (SELECT 1 FROM user_post_likes    upl2 WHERE upl2.postid = p.postid AND upl2.userid = al.userid) AS isliked,
				  EXISTS (SELECT 1 FROM user_post_views    upv2 WHERE upv2.postid = p.postid AND upv2.userid = al.userid) AS isviewed,
				  EXISTS (SELECT 1 FROM user_post_reports  upr2 WHERE upr2.postid = p.postid AND upr2.userid = al.userid) AS isreported,
				  EXISTS (SELECT 1 FROM user_post_dislikes upd2 WHERE upd2.postid = p.postid AND upd2.userid = al.userid) AS isdisliked,
				  EXISTS (SELECT 1 FROM user_post_saves    ups2 WHERE  ups2.postid = p.postid AND  ups2.userid = al.userid) AS issaved,
				  EXISTS (SELECT 1 FROM follows f WHERE f.followedid = p.userid AND f.followerid = al.userid) AS isfollowed,
				  EXISTS (SELECT 1 FROM follows f WHERE f.followerid = p.userid AND f.followedid = al.userid) AS isfollowing,
				  EXISTS (
					  SELECT 1 FROM follows f1
					  WHERE f1.followerid = al.userid AND f1.followedid = p.userid
						AND EXISTS (SELECT 1 FROM follows f2
									WHERE f2.followerid = p.userid AND f2.followedid = al.userid)
				  ) AS isfriend
			) m ON TRUE

			LEFT JOIN tags_by_post tg ON tg.postid = p.postid

			LEFT JOIN LATERAL (
			  SELECT COALESCE(
				  JSON_AGG(
					  JSON_BUILD_OBJECT(
						  'commentid',  c.commentid::text,
						  'postid',     c.postid::text,
						  'parentid',   c.parentid::text,
						  'content',    c.content,
						  'createdat',  c.createdat,
						  'amountlikes',   COALESCE(ci.likes, 0),
						  'amountreplies', COALESCE(ci.comments, 0),
						  'isliked', EXISTS (
							  SELECT 1 FROM user_comment_likes ucl
							  WHERE ucl.commentid = c.commentid
								AND ucl.userid    = al.userid
						  ),
						  'user', JSON_BUILD_OBJECT(
							  'uid',       cu.uid::text,
							  'username',  cu.username,
							  'slug',      cu.slug,
							  'img',       cu.img,
							  'isfollowed',  EXISTS (SELECT 1 FROM follows f WHERE f.followedid = cu.uid AND f.followerid = al.userid),
							  'isfollowing', EXISTS (SELECT 1 FROM follows f WHERE f.followerid = cu.uid AND f.followedid = al.userid),
							  'isfriend',    EXISTS (
								  SELECT 1 FROM follows f1
								  WHERE f1.followerid = al.userid
									AND f1.followedid = cu.uid
									AND EXISTS (
										SELECT 1 FROM follows f2
										WHERE  f2.followerid = cu.uid
										  AND  f2.followedid = al.userid
									)
							  )
						  )
					  )
					  ORDER BY c.createdat ASC
				  ),
				  '[]'::json
			  ) AS comments
			  FROM comments c
			  JOIN users cu ON cu.uid = c.userid
			  LEFT JOIN comment_info ci ON ci.commentid = c.commentid
			  WHERE c.postid = p.postid
			) cm ON TRUE

			ORDER BY $orderByClause, al.id DESC
			LIMIT :limit OFFSET :offset";

        $paramsStats = $paramsCommon;
        $paramsData  = $paramsCommon + [':trend_since' => $trendSince];

        try {
            // Stats
            $statsStmt = $this->db->prepare($sSql);
            foreach ($paramsStats as $k => $v) {
                if ($v !== null) {
                    $statsStmt->bindValue($k, $v);
                }
            }
            $statsStmt->execute();
            $stats = $statsStmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            $this->logger->info('fetchAllWithStats.statsStmt', ['statsStmt' => $stats]);

            // Data
            $dataStmt = $this->db->prepare($dSql);
            foreach ($paramsData as $k => $v) {
                if ($v !== null) {
                    $dataStmt->bindValue($k, $v);
                }
            }
            $dataStmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $dataStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $dataStmt->execute();
            $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);
            $this->logger->info('fetchAllWithStats.dataStmt', ['dataStmt' => $rows]);

            $ads = array_values(array_filter(array_map(function ($row) {
                return self::mapRowToAdvertisementt($row);
            }, $rows)));

            return [
                'affectedRows' => [
                    'stats' => [
                        'tokenSpent'     => (float)($stats['total_token_spent'] ?? 0),
                        'euroSpent'      => (float)($stats['total_euro_spent'] ?? 0),
                        'amountAds'      => (int)($stats['total_ads'] ?? 0),
                        'gemsEarned'     => (float)($stats['total_gems_earned'] ?? 0),
                        'amountLikes'    => (int)($stats['total_amount_likes'] ?? 0),
                        'amountViews'    => (int)($stats['total_amount_views'] ?? 0),
                        'amountComments' => (int)($stats['total_amount_comments'] ?? 0),
                        'amountDislikes' => (int)($stats['total_amount_dislikes'] ?? 0),
                        'amountReports'  => (int)($stats['total_amount_reports'] ?? 0),
                    ],
                    'advertisements' => $ads,
                ],
            ];
        } catch (\Throwable $e) {
            $this->logger->error("Error fetching advertisement stats or data", [
                'error' => $e->getMessage(),
            ]);
            return [
                'affectedRows' => [
                    'stats' => null,
                    'advertisements' => [],
                ],
            ];
        }
    }

    private static function mapRowToAdvertisementt(array $row): array
    {
        $tags = is_string($row['tags'] ?? null) ? json_decode($row['tags'], true) : ($row['tags'] ?? []);
        if (!is_array($tags)) {
            $tags = [];
        }

        $comments = is_string($row['comments'] ?? null) ? json_decode($row['comments'], true) : ($row['comments'] ?? []);
        if (!is_array($comments)) {
            $comments = [];
        }

        return [
            'advertisementid' => (string)$row['advertisementid'],
            'createdat'       => (string)$row['createdat'],
            'userid'          => (string)$row['creatorid'],
            'postid'          => (string)$row['postid'],
            'status'          => (string)$row['status'],
            'timestart'       => (string)$row['timeframestart'],
            'timeend'         => (string)$row['timeframeend'],
            'tokencost'       => (float)$row['totaltokencost'],
            'eurocost'        => (float)$row['totaleurocost'],
            'gemsearned'      => (float)$row['gemsearned'],
            'amountlikes'     => (int)$row['adsamountlikes'],
            'amountviews'     => (int)$row['adsamountviews'],
            'amountcomments'  => (int)$row['adsamountcomments'],
            'amountdislikes'  => (int)$row['adsamountdislikes'],
            'amountreports'   => (int)$row['adsamountreports'],

            'user' => [
                'uid'         => (string)$row['creatorid'],
                'username'    => (string)$row['creator_username'],
                'slug'        => (int)$row['creator_slug'],
                'img'         => (string)$row['creator_img'],
                'isfollowed'  => (bool)$row['isfollowed'],
                'isfollowing' => (bool)$row['isfollowing'],
                'isfriend'    => (bool)$row['isfriend'],
            ],

            'post' => [
                'postid'          => (string)$row['postid'],
                'contenttype'     => (string)$row['contenttype'],
                'title'           => (string)$row['title'],
                'media'           => (string)$row['media'],
                'cover'           => (string)$row['cover'],
                'mediadescription' => (string)$row['mediadescription'],
                'createdat'       => (string)$row['post_createdat'],
                'amountlikes'     => (int)$row['amountlikes'],
                'amountviews'     => (int)$row['amountviews'],
                'amountcomments'  => (int)$row['amountcomments'],
                'amountdislikes'  => (int)$row['amountdislikes'],
                'amounttrending'  => (int)$row['amounttrending'],
                'isliked'         => (bool)$row['isliked'],
                'isviewed'        => (bool)$row['isviewed'],
                'isreported'      => (bool)$row['isreported'],
                'isdisliked'      => (bool)$row['isdisliked'],
                'issaved'         => (bool)$row['issaved'],
                'url'             => (string)$_ENV['WEB_APP_URL'] . '/post/' . $row['postid'],
                'tags'            => $tags,
                'user' => [
                    'uid'         => (string)$row['post_owner_id'],
                    'username'    => (string)$row['post_username'],
                    'slug'        => (int)$row['post_slug'],
                    'img'         => (string)$row['post_userimg'],
                    'isfollowed'  => (bool)$row['isfollowed'],
                    'isfollowing' => (bool)$row['isfollowing'],
                    'isfriend'    => (bool)$row['isfriend'],
                ],
                'comments'        => $comments,
            ],
        ];
    }

    public function isAdvertisementDurationValid(string $postId, string $userId): bool
    {
        $this->logger->debug("AdvertisementMapper.isAdvertisementDurationValid started");

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
        $this->logger->debug("AdvertisementMapper.hasShortActiveAdWithUpcomingAd started", [
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
        $this->logger->debug("AdvertisementMapper.fetchByAdvID started", [
            'postId' => $postId,
            'status' => $status
        ]);

        $sql = "SELECT advertisementid, postid, userid, status, timestart, timeend, createdat 
                FROM advertisements 
                WHERE postid = :postId AND status = :status";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':postId', $postId, \PDO::PARAM_STR);
            $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
            $stmt->execute();

            $results = array_map(fn ($row) => new Advertisements($row), $stmt->fetchAll(\PDO::FETCH_ASSOC));

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

    public function advertisementExistsById(string $advertisementId): bool
    {
        $this->logger->debug("AdvertisementMapper.advertisementExistsById started");

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM advertisements WHERE advertisementId = :advertisementId");
        $stmt->bindValue(':advertisementId', $advertisementId, \PDO::PARAM_STR);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public function isAdvertisementIdExist(string $postId, string $status): bool
    {
        $this->logger->debug("AdvertisementMapper.isAdvertisementIdExist started");

        $sql = "SELECT 1 FROM advertisements WHERE postid = :postId AND status = :status LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':postId', $postId, \PDO::PARAM_STR);
        $stmt->bindValue(':status', $status, \PDO::PARAM_STR);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public function hasActiveAdvertisement(string $postId, string $status): bool
    {
        $this->logger->debug("AdvertisementMapper.hasActiveAdvertisement started", [
            'postId' => $postId,
            'status' => $status
        ]);

        $sql = "
            SELECT 1
            FROM advertisements
            WHERE postid   = :postId
              AND status   = :status
              AND timestart <= NOW()
              AND timeend   >= NOW()
            LIMIT 1
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':postId', $postId);
            $stmt->bindValue(':status', $status);
            $stmt->execute();

            $exists = (bool) $stmt->fetchColumn();

            $this->logger->info("Active advertisement check result", ['exists' => $exists]);

            return $exists;
        } catch (\Throwable $e) {
            $this->logger->error("Error checking active advertisement", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function hasTimeConflict(string $postId, string $status, string $newStart, string $newEnd, $currentUserId): bool
    {
        $this->logger->debug("AdvertisementMapper.hasTimeConflict started", [
            'postId' => $postId,
            'status' => $status,
            'newStart' => $newStart,
            'newEnd' => $newEnd
        ]);

        $sql = "
            SELECT 1
            FROM advertisements
            WHERE postid   = :postId
              AND userid   = :userid
              AND status   = :status
              AND timeend  > :newStart
              AND timestart < :newEnd
            LIMIT 1
        ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':postId', $postId);
            $stmt->bindValue(':userid', $currentUserId);
            $stmt->bindValue(':status', $status);
            $stmt->bindValue(':newStart', $newStart);
            $stmt->bindValue(':newEnd', $newEnd);
            $stmt->execute();

            $hasConflict = (bool) $stmt->fetchColumn();

            $this->logger->info("Conflict check result", ['conflict' => $hasConflict]);

            return $hasConflict; // true = Konflikt, false = frei
        } catch (\Throwable $e) {
            $this->logger->error("Error checking reservation conflicts", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    // Create a Post Advertisement with Loging
    public function insert(Advertisements $post): Advertisements
    {
        $this->logger->debug("AdvertisementMapper.insert started");

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

        $query3 = "INSERT INTO advertisements_info 
                   (advertisementid, postid, userid, updatedat, createdat)
                   VALUES 
                   (:advertisementid, :postid, :userid, :updatedat, :createdat) ON CONFLICT (advertisementid) DO NOTHING";

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

            // Statement 3
            $stmt3 = $this->db->prepare($query3);
            if (!$stmt3) {
                throw new \RuntimeException("SQL prepare() failed: " . implode(", ", $this->db->errorInfo()));
            }

            foreach (['advertisementid', 'postid', 'userid', 'updatedat', 'createdat'] as $key) {
                $stmt3->bindValue(':' . $key, $data[$key], \PDO::PARAM_STR);
            }

            $stmt3->execute();

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
        $this->logger->debug("AdvertisementMapper.update started");

        $data = $post->getArrayCopy();

        $query1 = "UPDATE advertisements 
                    SET timestart = :timestart, timeend = :timeend, userid = :userid
                    WHERE postid = :postid AND status = :status";

        $query2 = "INSERT INTO advertisements_log 
                    (advertisementid, postid, userid, status, timestart, timeend, tokencost, eurocost,createdat) 
                    VALUES (:advertisementid, :postid, :userid, :status, :timestart, :timeend, :tokencost, :eurocost,:createdat)";

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
            foreach (['advertisementid', 'postid', 'userid', 'status', 'timestart', 'timeend', 'tokencost', 'eurocost', 'createdat'] as $key) {
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
        $this->logger->debug('AdvertisementMapper.convertEuroToTokens started', ['euroAmount' => $euroAmount]);

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
        $this->logger->debug("AdvertisementMapper.findAdvertiser started");

        $offset = max((int)($args['offset'] ?? 0), 0);
        $limit  = min(max((int)($args['limit'] ?? 10), 1), 20);
        $trenddays = 7;

        /**
         * Includes Content Filtering for Reports Advertisements
         */
        $post_report_amount_to_hide     = ConstantsConfig::contentFiltering()['REPORTS_COUNT_TO_HIDE_FROM_IOS']['POST'];
        $post_dismiss_moderation_amount = ConstantsConfig::contentFiltering()['DISMISSING_MODERATION_COUNT_TO_RESTORE_TO_IOS']['POST'];
        $contentFilterBy = $args['contentFilterBy'] ?? null;

        $from   = $args['from']   ?? null;
        $to     = $args['to']     ?? null;
        $filterBy = $args['filterBy'] ?? [];
        $tag    = $args['tag']    ?? null;
        $postId = $args['postid'] ?? null;
        $userId = $args['userid'] ?? null;

        $contentFilterService = new ContentFilterServiceImpl(
            new ListPostsContentFilteringStrategy(),
            null,
            $contentFilterBy
        );

        $whereClauses = ["p.feedid IS NULL"];
        $params = ['currentUserId' => $currentUserId];

        // Show only Valid Content
        $whereClauses[] = "p.status IN (:postNormalStatus, :postAdvertisementStatus)";
        $params['postNormalStatus'] = ConstantsConfig::post()['STATUS']['PUBLISHED'];
        $params['postAdvertisementStatus'] = ConstantsConfig::post()['STATUS']['ADVERTISED'];


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
        // Show only Valid Content
        // $whereClauses[] = "p.status = :postStatus";
        // $params['postStatus'] = ConstantsConfig::post()['STATUS']['ADVERTISED'];

        // Allow Only (Normal Status) Plus (User's & Admin's Mode) Posts
        $whereClauses[] = 'u.status = :stNormal AND u.roles_mask IN (:roleUser, :roleAdmin)';
        $params['stNormal']  = Status::NORMAL;
        $params['roleUser']  = Role::USER;
        $params['roleAdmin'] = Role::ADMIN;

        // Content Filtering
        if ($contentFilterService->getContentFilterAction(
            ContentType::post,
            ContentType::post
        ) === ContentFilteringAction::hideContent) {
            $params['post_report_amount_to_hide']     = $post_report_amount_to_hide;
            $params['post_dismiss_moderation_amount'] = $post_dismiss_moderation_amount;
            $whereClauses[] = '(
                p.userid = :currentUserId
                OR (
                    pi.reports < :post_report_amount_to_hide
                    OR pi.count_content_moderation_dismissed > :post_dismiss_moderation_amount
                )
            )';
        }

        // FilterBy Content Types
        // FilterBy Content Types (reuse helper for mapping)
        if (!empty($filterBy) && is_array($filterBy)) {
            $dbTypes = ContentFilterHelper::mapContentTypesForDb($filterBy);

            if (!empty($dbTypes)) {
                $placeholders = [];
                foreach (array_values($dbTypes) as $i => $value) {
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
                EXISTS (SELECT 1 FROM follows WHERE followerid = p.userid AND followedid = :currentUserId) AS isfollowing,
                MAX(ui.count_content_moderation_dismissed) AS user_count_content_moderation_dismissed,
                MAX(pi.count_content_moderation_dismissed) AS post_count_content_moderation_dismissed,
                MAX(ui.reports) AS user_reports,
                MAX(pi.reports) AS post_reports
            FROM posts p
            JOIN users u ON p.userid = u.uid
            LEFT JOIN post_tags pt ON p.postid = pt.postid
            LEFT JOIN tags t ON pt.tagid = t.tagid
            LEFT JOIN advertisements a ON p.postid = a.postid
            LEFT JOIN users ut ON a.userid = ut.uid
            LEFT JOIN post_info pi ON pi.postid = p.postid AND pi.userid = p.userid
            LEFT JOIN users_info ui ON ui.userid = p.userid
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
        SELECT *
        FROM base_posts bp
        WHERE bp.ad_type = 'basic'
          AND bp.ad_order <= NOW()
          AND bp.end_order > NOW()
          AND NOT EXISTS (
            SELECT 1
            FROM advertisements a2
            WHERE a2.postid = bp.postid
              AND a2.userid = bp.userid
              AND a2.status = 'pinned'
              AND a2.timestart <= NOW()
              AND a2.timeend  > NOW()
          )
        ORDER BY bp.ad_order ASC
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

            return array_map(function ($row) use ($contentFilterService, $currentUserId) {
                $row['tags'] = json_decode($row['tags'], true) ?? [];

                // User-Placeholder anwenden, falls nötig
                $user_reports = (int)$row['user_reports'];
                $user_dismiss = (int)$row['user_count_content_moderation_dismissed'];

                if ($contentFilterService->getContentFilterAction(
                    ContentType::post,
                    ContentType::user,
                    $user_reports,
                    $user_dismiss,
                    $currentUserId,
                    $row['userid']
                ) === ContentFilteringAction::replaceWithPlaceholder) {
                    $replacer       = ContentReplacementPattern::flagged;
                    $row['username'] = $replacer->username($row['username']);
                    $row['userimg'] = $replacer->profilePicturePath($row['userimg']);
                }

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
                'slug' => (int)$row['tslug'],
                'img' => (string)$row['timg'],
                'isfollowed' => (bool)$row['tisfollowed'],
                'isfollowing' => (bool)$row['tisfollowing'],
                'isfriend' => (bool)((int)$row['tisfollowed'] && (int)$row['tisfollowing']),
            ],
        ]);
    }
}
