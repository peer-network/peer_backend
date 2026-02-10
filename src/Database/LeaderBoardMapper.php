<?php

declare(strict_types=1);

namespace Fawaz\Database;

use Fawaz\Utils\PeerLoggerInterface;
use Fawaz\Utils\ResponseHelper;
use PDO;

class LeaderBoardMapper
{
    use ResponseHelper;

    public function __construct(protected PDO $db, protected PeerLoggerInterface $logger)
    {
    }

    /**
     * Prepare query and get result from DB
     *
     * @param string $start_date
     * @param string $end_date
     * @param int $leaderboardUsersCount
     * @return array
     */
    public function getLeaderboardResult(string $start_date, string $end_date, int $leaderboardUsersCount): array
    {

        $this->logger->debug("LeaderBoardMapper.getLeaderboardResult started");

        $sql = "WITH date_range AS (
                    SELECT CAST(:start_date AS timestamp) AS start_date,
                        CAST(:end_date AS timestamp) AS end_date
                ),
                ppc_tag AS (
                    SELECT tagid
                    FROM tags
                    WHERE name = 'ppc'
                    LIMIT 1
                ),
                post_engagement AS (
                    SELECT p.userid,
                        SUM(pi.comments) AS comments_on_posts,
                        SUM(pi.likes) AS likes_on_posts
                    FROM posts p
                    JOIN post_info pi ON pi.postid = p.postid AND pi.userid = p.userid
                    JOIN date_range dr ON pi.createdat >= dr.start_date AND pi.createdat <= dr.end_date
                    WHERE EXISTS (
                        SELECT 1
                        FROM post_tags pt
                        JOIN ppc_tag t ON t.tagid = pt.tagid
                        WHERE pt.postid = p.postid and pt.tagid = t.tagid
                    )
                    GROUP BY p.userid
                ),
                ppc_posts AS (
                    SELECT p.userid,
                        SUM(
                            CASE
                                WHEN p.contenttype = 'image' THEN 2 -- 2 points per #PPC post's tag 
                                WHEN p.contenttype = 'video' THEN 5 -- 5 points per #PPC post's tag
                                ELSE 0
                            END
                        ) AS ppc_points
                    FROM posts p
                    JOIN post_tags pt ON pt.postid = p.postid
                    JOIN ppc_tag t ON t.tagid = pt.tagid
                    JOIN date_range dr ON p.createdat >= dr.start_date AND p.createdat <= dr.end_date
                    GROUP BY p.userid
                ),
                likes_given AS (
                    SELECT upl.userid,
                        COUNT(*) AS likes_given
                    FROM user_post_likes upl
                    JOIN date_range dr ON upl.createdat >= dr.start_date AND upl.createdat <= dr.end_date
                    GROUP BY upl.userid
                ),
                comments_given AS (
                    SELECT c.userid,
                        COUNT(*) AS comments_given
                    FROM comments c
                    JOIN date_range dr ON c.createdat >= dr.start_date AND c.createdat <= dr.end_date
                    GROUP BY c.userid
                ),
                referrals AS (
                    SELECT ui.invited AS userid,
                        COUNT(*) AS referrals
                    FROM users_info ui
                    JOIN users u_inv ON u_inv.uid = ui.userid
                    JOIN date_range dr ON u_inv.createdat >= dr.start_date AND u_inv.createdat <= dr.end_date
                    WHERE ui.invited IS NOT NULL
                    GROUP BY ui.invited
                )
                SELECT u.uid,
                    u.username,
                    u.slug,
                    COALESCE(pe.comments_on_posts, 0) AS comments_on_posts,
                    COALESCE(pe.likes_on_posts, 0) AS likes_on_posts,
                    COALESCE(ppc.ppc_points, 0) AS ppc_points,
                    COALESCE(lg.likes_given, 0) AS likes_given,
                    COALESCE(cg.comments_given, 0) AS comments_given,
                    COALESCE(rf.referrals, 0) AS referrals,
                    COALESCE(pe.comments_on_posts, 0) * 1 -- 1 Point on each comment on posts of you
                    + COALESCE(pe.likes_on_posts, 0) * 2 -- 2 point on each like posts of you
                    + COALESCE(ppc.ppc_points, 0)
                    + COALESCE(lg.likes_given, 0) * 1 -- 1 Point of each like on another's post
                    + COALESCE(cg.comments_given, 0) * 2 -- 2 points of each comment on another's post
                    + COALESCE(rf.referrals, 0) * 10 -- 10 points for each referral
                AS total_points
                FROM users u
                LEFT JOIN post_engagement pe ON pe.userid = u.uid
                LEFT JOIN ppc_posts ppc ON ppc.userid = u.uid
                LEFT JOIN likes_given lg ON lg.userid = u.uid
                LEFT JOIN comments_given cg ON cg.userid = u.uid
                LEFT JOIN referrals rf ON rf.userid = u.uid
                ORDER BY total_points DESC, u.username ASC 
                LIMIT :limit;
            ";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':start_date', $start_date . ' 00:00:00');
            $stmt->bindValue(':end_date', $end_date . ' 23:59:59.999999');
            $stmt->bindValue(':limit', $leaderboardUsersCount, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $results;

        } catch (\Throwable $e) {
            $this->logger->error('General error in LeaderBoardMapper.getLeaderboardResult', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

}
